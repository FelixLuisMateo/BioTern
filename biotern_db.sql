-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 18, 2026 at 03:24 AM
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
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) NOT NULL,
  `institution_email_address` varchar(255) NOT NULL,
  `phone_number` varchar(255) NOT NULL,
  `admin_level` varchar(255) NOT NULL,
  `department_id` int(11) NOT NULL,
  `admin_position` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_letter`
--

CREATE TABLE `application_letter` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `application_person` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_letter`
--

INSERT INTO `application_letter` (`id`, `user_id`, `date`, `application_person`, `position`, `company_name`, `company_address`) VALUES
(12, 1, '2026-02-26', 'Ivan Sanchez', 'Human Resources', 'Biotern', 'Aurea St. Samsonville, Dau, Mabalacat City, Pampanga');

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
(1, 1, NULL, '2026-02-24', '11:17:00', '11:17:00', '11:18:00', '11:18:00', '11:18:00', '11:18:00', 0.00, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-24 03:17:45', '2026-02-24 03:18:22'),
(2, 1, NULL, '2026-02-26', NULL, NULL, NULL, NULL, '11:01:00', '16:26:00', 5.42, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-26 03:01:08', '2026-02-26 08:26:30'),
(3, 2, NULL, '2026-02-26', '16:28:00', NULL, NULL, NULL, '14:25:00', '16:25:00', 2.00, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-26 06:25:25', '2026-02-26 08:28:05'),
(4, 1, NULL, '2026-02-27', NULL, NULL, NULL, NULL, '14:23:00', '18:57:00', 4.57, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-27 06:23:04', '2026-02-27 10:57:23'),
(5, 1, NULL, '2026-02-28', '08:08:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-28 00:08:52', '2026-02-28 00:08:52'),
(6, 1, NULL, '2026-03-02', '08:39:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-02 00:39:25', '2026-03-02 00:39:25'),
(7, 1, NULL, '2026-03-03', '11:10:00', '15:54:00', NULL, NULL, '15:55:00', '18:58:00', 7.78, 'manual', 'approved', 4, '2026-03-03 03:58:50', NULL, NULL, NULL, NULL, '2026-03-03 03:10:50', '2026-03-03 10:58:50'),
(8, 1, NULL, '2026-03-04', '14:24:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04 06:24:40', '2026-03-04 06:24:40'),
(9, 1, NULL, '2026-03-05', '10:46:00', '16:05:00', NULL, NULL, NULL, NULL, 5.32, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-05 02:46:19', '2026-03-05 08:05:28'),
(11, 4, NULL, '2026-03-05', '16:12:00', NULL, NULL, NULL, '16:10:00', NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-05 08:12:21', '2026-03-05 08:12:51'),
(12, 4, NULL, '2026-03-06', '14:28:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-06 06:28:54', '2026-03-06 06:28:54'),
(13, 1, NULL, '2026-03-09', '10:46:00', NULL, NULL, NULL, '10:46:00', NULL, 0.00, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-09 02:46:22', '2026-03-09 02:46:31'),
(14, 1, NULL, '2026-03-11', '08:08:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 00:08:43', '2026-03-11 00:08:43'),
(15, 1, NULL, '2026-03-17', '15:44:00', NULL, NULL, NULL, NULL, NULL, NULL, 'manual', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-17 07:44:30', '2026-03-17 07:44:30');

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
(6, 2, 'Mark', 'Verzon', NULL, 'markverzon@gmail.com', '9091734512', 1, 'IT Faculty', NULL, NULL, 1, '2026-02-24 02:56:30', '2026-02-24 02:56:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `coordinator_courses`
--

CREATE TABLE `coordinator_courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coordinator_user_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coordinator_courses`
--

INSERT INTO `coordinator_courses` (`id`, `coordinator_user_id`, `course_id`, `created_at`) VALUES
(1, 2, 1, '2026-03-09 01:57:44');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `course_head` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `name`, `code`, `course_head`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Associate in Computer Technology', 'ACT', 'Jomar G. Sangil', '2026-02-24 01:31:25', '2026-02-24 01:31:25', NULL),
(2, 'Bachelor of Science in Office Administration', 'BSOA', 'Juan Dela Cruz', '2026-02-24 02:51:14', '2026-02-26 06:14:16', NULL),
(5, 'Hospitality Management and Technology', 'HMT', 'Pedro Penduko', '2026-03-18 02:21:51', '2026-03-18 02:21:51', NULL),
(6, 'Bachelor of Science in Entrepreneurship', 'BSE', 'Nathaniel Dizon', '2026-03-18 02:22:34', '2026-03-18 02:22:34', NULL),
(7, 'Computer Technology', 'CT', 'Superman', '2026-03-18 02:23:08', '2026-03-18 02:23:08', NULL);

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
-- Table structure for table `dau_moa`
--

CREATE TABLE `dau_moa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `partner_representative` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `company_receipt` varchar(255) DEFAULT NULL,
  `total_hours` varchar(50) DEFAULT NULL,
  `school_representative` varchar(255) DEFAULT NULL,
  `school_position` varchar(255) DEFAULT NULL,
  `signed_at` varchar(255) DEFAULT NULL,
  `signed_day` varchar(20) DEFAULT NULL,
  `signed_month` varchar(30) DEFAULT NULL,
  `signed_year` varchar(10) DEFAULT NULL,
  `witness_partner` varchar(255) DEFAULT NULL,
  `school_administrator` varchar(255) DEFAULT NULL,
  `school_admin_position` varchar(255) DEFAULT NULL,
  `notary_city` varchar(255) DEFAULT NULL,
  `notary_day` varchar(20) DEFAULT NULL,
  `notary_month` varchar(30) DEFAULT NULL,
  `notary_year` varchar(10) DEFAULT NULL,
  `notary_place` varchar(255) DEFAULT NULL,
  `doc_no` varchar(100) DEFAULT NULL,
  `page_no` varchar(100) DEFAULT NULL,
  `book_no` varchar(100) DEFAULT NULL,
  `series_no` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dau_moa`
--

INSERT INTO `dau_moa` (`id`, `user_id`, `company_name`, `company_address`, `partner_representative`, `position`, `company_receipt`, `total_hours`, `school_representative`, `school_position`, `signed_at`, `signed_day`, `signed_month`, `signed_year`, `witness_partner`, `school_administrator`, `school_admin_position`, `notary_city`, `notary_day`, `notary_month`, `notary_year`, `notary_place`, `doc_no`, `page_no`, `book_no`, `series_no`, `created_at`, `updated_at`) VALUES
(1, 1, 'BIODERM', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-02-27 15:02:54', '2026-02-27 15:31:07');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `department_head` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `department_head`, `contact_email`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Information Technology', 'DEPT-IT', 'Jomar G. Sangil', 'jomar.sangil@clarkcollege.edu.ph', '2026-02-23 10:08:58', '2026-02-26 06:11:44', NULL);

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
-- Table structure for table `document_workflow`
--

CREATE TABLE `document_workflow` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `doc_type` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `review_notes` text DEFAULT NULL,
  `approved_by` int(11) NOT NULL DEFAULT 0,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `endorsement`
--

CREATE TABLE `endorsement` (
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `endorsement_letter`
--

CREATE TABLE `endorsement_letter` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_title` varchar(20) DEFAULT 'none',
  `recipient_position` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `students_to_endorse` text DEFAULT NULL,
  `greeting_preference` varchar(20) DEFAULT 'either',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `endorsement_letter`
--

INSERT INTO `endorsement_letter` (`id`, `user_id`, `recipient_name`, `recipient_title`, `recipient_position`, `company_name`, `company_address`, `students_to_endorse`, `greeting_preference`, `created_at`, `updated_at`) VALUES
(1, 1, 'Jomer De Guzman', 'auto', 'Supervisor', 'Biotern', 'Aurea St. Samsonville, Dau, Mabalacat City, Pampanga', 'Felix Luis Manaloto Mateo\r\nTyron Jay Timbol Gonzales', 'either', '2026-02-27 15:11:54', '2026-03-03 18:10:41');

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
-- Table structure for table `evaluation_unlocks`
--

CREATE TABLE `evaluation_unlocks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `internship_id` int(11) DEFAULT NULL,
  `is_unlocked` tinyint(1) NOT NULL DEFAULT 0,
  `unlocked_at` datetime DEFAULT NULL,
  `unlocked_by` int(11) DEFAULT NULL,
  `unlock_source` varchar(30) NOT NULL DEFAULT 'manual',
  `unlock_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(7, 1, 1, 1, 2, 1, 'internal', NULL, NULL, NULL, '2026-02-24', NULL, NULL, 'ongoing', '2026-2027', 140, 23, 16.49, '2026-02-24 03:05:20', '2026-03-17 07:44:30', NULL),
(8, 2, 1, 1, 2, 1, 'internal', NULL, NULL, NULL, '2026-02-26', NULL, NULL, 'ongoing', '2026-2027', 140, 2, 1.43, '2026-02-26 06:25:05', '2026-02-26 08:28:05', NULL),
(9, 4, 1, 1, 2, 1, 'internal', NULL, NULL, NULL, '2026-03-05', NULL, NULL, 'ongoing', '2026-2027', 1, 0, 0.00, '2026-03-05 08:04:24', '2026-03-06 06:28:54', NULL),
(10, 6, 1, 1, 2, 1, 'internal', NULL, NULL, NULL, '2026-03-10', NULL, NULL, 'ongoing', '2026-2027', 140, 0, 0.00, '2026-03-10 07:18:34', '2026-03-10 07:18:34', NULL),
(11, 7, 1, 1, 2, 1, 'internal', NULL, NULL, NULL, '2026-03-14', NULL, NULL, 'ongoing', '2025-2026', 140, 0, 0.00, '2026-03-14 11:58:15', '2026-03-14 11:58:15', NULL),
(12, 8, 1, 1, 2, 1, 'external', NULL, NULL, NULL, '2026-03-17', NULL, NULL, 'ongoing', '2025-2026', 250, 0, 0.00, '2026-03-17 07:40:35', '2026-03-17 07:43:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `identifier` varchar(191) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `identifier`, `role`, `status`, `reason`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 3, 'FelixLuisMateo', 'student', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 13:27:38'),
(2, 3, 'FelixLuisMateo', 'student', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 13:29:13'),
(3, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 13:29:35'),
(4, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 13:33:12'),
(5, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 13:35:28'),
(6, 4, 'Jomar', 'admin', 'failed', 'invalid_credentials', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 13:35:32'),
(7, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 13:35:35'),
(8, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 13:35:35'),
(9, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 14:11:52'),
(10, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 14:11:53'),
(11, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 14:12:15'),
(12, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 08:12:32'),
(13, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 08:12:34'),
(14, 8, 'NavenCuenca', 'student', 'failed', 'pending_approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 08:49:30'),
(15, 8, 'NavenCuenca', 'student', 'failed', 'pending_approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 09:03:02'),
(16, 8, 'NavenCuenca', 'student', 'failed', 'pending_approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 09:14:26'),
(17, 8, 'NavenCuenca', 'student', 'failed', 'pending_approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 10:02:23'),
(18, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 11:15:55'),
(19, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 11:15:58'),
(20, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 13:29:24'),
(21, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 13:31:16'),
(22, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 13:31:24'),
(23, 7, 'VonLopez', 'student', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 13:31:32'),
(24, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 13:31:55'),
(25, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 13:40:36'),
(26, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 14:52:31'),
(27, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 07:59:03'),
(28, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 08:01:30'),
(29, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 08:08:27'),
(30, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 08:17:23'),
(31, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 08:37:07'),
(32, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 14:19:41'),
(33, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 14:21:31'),
(34, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 14:35:15'),
(35, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 16:31:14'),
(36, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 08:22:52'),
(37, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 08:46:01'),
(38, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 08:47:02'),
(39, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 08:49:10'),
(40, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 11:53:16'),
(41, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 13:12:02'),
(42, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 16:01:40'),
(43, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 16:01:44'),
(44, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 16:01:44'),
(45, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 15:34:32'),
(46, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 15:34:32'),
(47, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 15:34:33'),
(48, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 15:34:39'),
(49, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 15:50:52'),
(50, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 18:24:04'),
(51, 4, 'jomar', 'admin', 'success', 'login_success', '192.168.100.85', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 19:52:58'),
(52, 4, 'Jomar', 'admin', 'success', 'login_success', '192.168.100.190', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-14 19:59:30'),
(53, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 20:23:40'),
(54, 4, 'Jomar', 'admin', 'success', 'login_success', '192.168.100.190', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-14 21:16:46'),
(55, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-15 19:28:53'),
(56, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-15 19:28:58'),
(57, 4, 'jomar', 'admin', 'failed', 'invalid_credentials', '192.168.100.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 19:38:34'),
(58, 4, 'jomar', 'admin', 'success', 'login_success', '192.168.100.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 19:38:43'),
(59, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 13:10:43'),
(60, 4, 'jomar', 'admin', 'success', 'login_success', '192.168.110.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 13:22:48'),
(61, NULL, 'Testo', '', 'failed', 'invalid_credentials', '192.168.110.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 OPR/128.0.0.0', '2026-03-17 13:44:21'),
(62, 10, 'Lucky', 'student', 'success', 'login_success', '192.168.110.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 13:45:27'),
(63, 4, 'jomarsangil@gmail.com', 'admin', 'success', 'login_success', '192.168.110.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 OPR/128.0.0.0', '2026-03-17 13:46:35'),
(64, 10, 'Lucky', 'admin', 'success', 'login_success', '192.168.110.143', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 13:47:09'),
(65, 4, 'Jomar', 'admin', 'success', 'login_success', '192.168.110.79', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 13:54:11'),
(66, 4, 'Jomar', 'admin', 'success', 'login_success', '192.168.110.143', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 14:16:58'),
(67, 4, 'Jomar', 'admin', 'failed', 'invalid_credentials', '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:15:51'),
(68, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:15:59'),
(69, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:17:20'),
(70, NULL, 'ivan', '', 'failed', 'invalid_credentials', '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:28:15'),
(71, 4, 'jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:28:31'),
(72, NULL, '\' or 1 = 1 --', '', 'failed', 'invalid_credentials', '192.168.110.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 15:32:30'),
(73, NULL, '\' or 1 = 1 --', '', 'failed', 'invalid_credentials', '192.168.110.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 15:32:57'),
(74, 4, 'Jomar', 'admin', 'success', 'login_success', '192.168.110.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 15:33:09'),
(75, 12, 'ivan', 'admin', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:41:01'),
(76, NULL, 'ivan 123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:41:16'),
(77, NULL, 'ivan 123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:41:20'),
(78, NULL, 'ivan 123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:41:26'),
(79, NULL, 'ivan 123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:41:28'),
(80, NULL, 'ivan123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:41:37'),
(81, NULL, 'ivan123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:41:40'),
(82, NULL, 'ivan123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:41:51'),
(83, NULL, 'ivan 123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:42:07'),
(84, NULL, 'ivan 123', '', 'failed', 'invalid_credentials', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:45:38'),
(85, 12, 'work.ivansanchez@gmail.com', 'admin', 'success', 'login_success', '192.168.110.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-17 15:46:11'),
(86, NULL, '05-0702', '', 'failed', 'invalid_credentials', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-17 15:51:25'),
(87, 12, 'work.ivansanchez@gmail.com', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-17 15:51:37'),
(88, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 16:05:08'),
(89, 12, 'work.ivansanchez@gmail.com', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-17 16:06:00'),
(90, 3, 'mateo.felixluis@gmail.com', 'student', 'failed', 'invalid_credentials', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 16:06:02'),
(91, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 16:06:18'),
(92, 4, 'Jomar', 'admin', 'success', 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 09:59:08');

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
  `reply_to_message_id` bigint(20) UNSIGNED DEFAULT NULL,
  `media_path` varchar(512) DEFAULT NULL,
  `reaction_emoji` varchar(32) DEFAULT NULL,
  `reaction_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `from_user_id`, `to_user_id`, `subject`, `message`, `reply_to_message_id`, `media_path`, `reaction_emoji`, `reaction_by_user_id`, `is_read`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 4, 3, 'BioTern Chat', 'hiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii', NULL, NULL, NULL, NULL, 0, '2026-03-13 10:25:56', '2026-03-13 10:25:56', NULL),
(2, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:00', '2026-03-17 05:46:00', NULL),
(3, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:12', '2026-03-17 05:46:12', NULL),
(4, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:12', '2026-03-17 05:46:12', NULL),
(5, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:12', '2026-03-17 05:46:12', NULL),
(6, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:13', '2026-03-17 05:46:13', NULL),
(7, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:13', '2026-03-17 05:46:13', NULL),
(8, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:13', '2026-03-17 05:46:13', NULL),
(9, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:13', '2026-03-17 05:46:13', NULL),
(10, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:13', '2026-03-17 05:46:13', NULL),
(11, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:15', '2026-03-17 05:46:15', NULL),
(12, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:18', '2026-03-17 05:46:18', NULL),
(13, 10, 4, 'BioTern Chat', 'sir', NULL, NULL, NULL, NULL, 1, '2026-03-17 05:46:19', '2026-03-17 05:46:19', NULL),
(14, 10, 4, 'BioTern Chat', 'boi', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:17:17', '2026-03-17 06:17:17', NULL),
(15, 10, 4, 'BioTern Chat', 'seen', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:17:32', '2026-03-17 06:17:32', NULL),
(16, 4, 10, 'BioTern Chat', '👍', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:19:01', '2026-03-17 06:19:01', NULL),
(17, 4, 10, 'BioTern Chat', '👍', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:19:02', '2026-03-17 06:19:02', NULL),
(18, 4, 10, 'BioTern Chat', '👍', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:19:07', '2026-03-17 06:19:07', NULL),
(19, 4, 10, 'BioTern Chat', 'He', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:19:54', '2026-03-17 06:19:54', NULL),
(20, 4, 10, 'BioTern Chat', 'Fbf', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:20:02', '2026-03-17 06:20:02', NULL),
(21, 10, 4, 'BioTern Chat', 'hello', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:20:07', '2026-03-17 06:20:07', NULL),
(22, 4, 10, 'BioTern Chat', './.', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:20:16', '2026-03-17 06:20:57', '2026-03-17 06:20:57'),
(23, 4, 10, 'BioTern Chat', 'Hi', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:21:14', '2026-03-17 06:21:14', NULL),
(24, 4, 10, 'BioTern Chat', '👍', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:25:28', '2026-03-17 06:25:28', NULL),
(25, 10, 4, 'BioTern Chat', '👍', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:26:11', '2026-03-17 06:26:11', NULL),
(26, 4, 10, 'BioTern Chat', 'H', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:27:43', '2026-03-17 06:27:43', NULL),
(27, 4, 10, 'BioTern Chat', 'B\r\nH', NULL, NULL, NULL, NULL, 1, '2026-03-17 06:29:53', '2026-03-17 06:29:53', NULL),
(28, 4, 3, 'BioTern Chat', 'J', NULL, NULL, NULL, NULL, 0, '2026-03-17 06:31:09', '2026-03-17 06:31:09', NULL),
(29, 4, 3, 'BioTern Chat', 'Hello', NULL, NULL, NULL, NULL, 0, '2026-03-17 07:15:32', '2026-03-17 07:15:32', NULL),
(30, 4, 10, 'BioTern Chat', 'Hiii', NULL, NULL, NULL, NULL, 1, '2026-03-17 07:15:58', '2026-03-17 07:15:58', NULL),
(31, 10, 4, 'BioTern Chat', 'hello po', NULL, NULL, NULL, NULL, 1, '2026-03-17 07:16:06', '2026-03-17 07:16:06', NULL),
(32, 4, 10, 'BioTern Chat', 'Hi', NULL, NULL, NULL, NULL, 1, '2026-03-17 07:16:56', '2026-03-17 07:16:56', NULL),
(33, 4, 10, 'BioTern Chat', 'Hello', NULL, NULL, NULL, NULL, 1, '2026-03-17 07:17:17', '2026-03-17 07:17:17', NULL),
(34, 12, 4, 'BioTern Chat', 'hi', NULL, NULL, NULL, NULL, 1, '2026-03-17 08:06:24', '2026-03-17 08:06:24', NULL),
(35, 4, 12, 'BioTern Chat', 'Bakit?', NULL, NULL, NULL, NULL, 1, '2026-03-17 08:06:36', '2026-03-17 08:06:36', NULL),
(36, 12, 4, 'BioTern Chat', 'burat', NULL, NULL, NULL, NULL, 1, '2026-03-17 08:06:39', '2026-03-17 08:06:39', NULL),
(37, 12, 4, 'BioTern Chat', '🖕', NULL, NULL, NULL, NULL, 1, '2026-03-17 08:08:34', '2026-03-17 08:09:13', '2026-03-17 08:09:13'),
(38, 4, 12, 'BioTern Chat', 'bawal yan', NULL, NULL, NULL, NULL, 1, '2026-03-17 08:08:49', '2026-03-17 08:08:49', NULL),
(39, 4, 12, 'BioTern Chat', 'disciplinary ka mapupunta', NULL, NULL, NULL, NULL, 1, '2026-03-17 08:09:04', '2026-03-17 08:09:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `message_pins`
--

CREATE TABLE `message_pins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `pinned_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `emoji` varchar(32) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_reactions`
--

INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `emoji`, `created_at`, `updated_at`) VALUES
(1, 7, 10, '❤️', '2026-03-17 06:11:31', '2026-03-17 06:11:31'),
(2, 15, 4, '😂', '2026-03-17 06:19:47', '2026-03-17 06:19:47'),
(3, 36, 4, '😡', '2026-03-17 08:07:19', '2026-03-17 08:07:30');

-- --------------------------------------------------------

--
-- Table structure for table `message_reports`
--

CREATE TABLE `message_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `reporter_user_id` bigint(20) UNSIGNED NOT NULL,
  `reported_user_id` bigint(20) UNSIGNED NOT NULL,
  `reason` varchar(255) NOT NULL DEFAULT 'Inappropriate message',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `moderator_note` varchar(255) DEFAULT NULL,
  `reviewed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_reports`
--

INSERT INTO `message_reports` (`id`, `message_id`, `reporter_user_id`, `reported_user_id`, `reason`, `status`, `moderator_note`, `reviewed_by_user_id`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 36, 4, 12, 'Sexual content', 'open', NULL, NULL, NULL, '2026-03-18 02:00:48', '2026-03-18 02:00:48');

-- --------------------------------------------------------

--
-- Table structure for table `moa`
--

CREATE TABLE `moa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_address` varchar(255) NOT NULL,
  `company_receipt` varchar(255) NOT NULL,
  `doc_no` varchar(100) NOT NULL,
  `page_no` varchar(100) NOT NULL,
  `book_no` varchar(100) NOT NULL,
  `series_no` varchar(100) NOT NULL,
  `total_hours` varchar(50) NOT NULL,
  `moa_address` varchar(255) NOT NULL,
  `moa_date` date NOT NULL,
  `coordinator` varchar(255) NOT NULL,
  `school_posistion` varchar(255) NOT NULL,
  `school_position` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `partner_representative` varchar(255) NOT NULL,
  `school_administrator` varchar(255) NOT NULL,
  `school_admin_position` varchar(255) NOT NULL,
  `notary_address` varchar(255) NOT NULL,
  `witness` varchar(255) NOT NULL,
  `acknowledgement_date` date NOT NULL,
  `acknowledgement_address` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `moa`
--

INSERT INTO `moa` (`id`, `user_id`, `company_name`, `company_address`, `company_receipt`, `doc_no`, `page_no`, `book_no`, `series_no`, `total_hours`, `moa_address`, `moa_date`, `coordinator`, `school_posistion`, `school_position`, `position`, `partner_representative`, `school_administrator`, `school_admin_position`, `notary_address`, `witness`, `acknowledgement_date`, `acknowledgement_address`) VALUES
(7, 1, 'Biotern', 'SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga', 'Jomer De Guzman', '', '', '', '', '250', 'SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga', '2026-03-04', 'Naven Cuenca', 'Head IT Admin', '', 'Admin', 'Jomer De Guzman', 'Ivan Sanchez', 'School Administrator', 'SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga', 'Tyron Gonzales', '2026-03-04', 'SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` longtext DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'system',
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `action_url`, `is_read`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 3, 'Attendance Approved', 'Your attendance entry was approved.', 'system', NULL, 0, '2026-03-03 10:58:50', '2026-03-03 10:58:50', NULL),
(2, 3, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 0, '2026-03-13 10:25:56', NULL, NULL),
(3, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:00', NULL, NULL),
(4, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:12', NULL, NULL),
(5, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:12', NULL, NULL),
(6, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:12', NULL, NULL),
(7, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:13', NULL, NULL),
(8, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:13', NULL, NULL),
(9, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:13', NULL, NULL),
(10, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:13', NULL, NULL),
(11, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:13', NULL, NULL),
(12, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:15', NULL, NULL),
(13, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:18', NULL, NULL),
(14, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 05:46:19', NULL, NULL),
(15, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 06:17:17', NULL, NULL),
(16, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 06:17:32', NULL, NULL),
(17, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:19:01', NULL, NULL),
(18, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:19:02', NULL, NULL),
(19, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:19:07', NULL, NULL),
(20, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:19:54', NULL, NULL),
(21, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:20:02', NULL, NULL),
(22, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 06:20:07', NULL, NULL),
(23, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:20:16', NULL, NULL),
(24, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:21:14', NULL, NULL),
(25, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:25:28', NULL, NULL),
(26, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 06:26:11', NULL, NULL),
(27, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:27:43', NULL, NULL),
(28, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 06:29:53', NULL, NULL),
(29, 3, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 0, '2026-03-17 06:31:09', NULL, NULL),
(30, 3, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 0, '2026-03-17 07:15:32', NULL, NULL),
(31, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 07:15:58', NULL, NULL),
(32, 4, 'New chat message', 'Lucky Mateo sent you a message.', 'message', 'apps-chat.php?user_id=10', 1, '2026-03-17 07:16:06', NULL, NULL),
(33, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 07:16:56', NULL, NULL),
(34, 10, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 07:17:17', NULL, NULL),
(35, 4, 'New chat message', 'Ivan 123 sent you a message.', 'message', 'apps-chat.php?user_id=12', 1, '2026-03-17 08:06:24', NULL, NULL),
(36, 12, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 08:06:36', NULL, NULL),
(37, 4, 'New chat message', 'Ivan 123 sent you a message.', 'message', 'apps-chat.php?user_id=12', 1, '2026-03-17 08:06:39', NULL, NULL),
(38, 4, 'New chat message', 'Ivan 123 sent you a message.', 'message', 'apps-chat.php?user_id=12', 1, '2026-03-17 08:08:34', NULL, NULL),
(39, 12, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 08:08:49', NULL, NULL),
(40, 12, 'New chat message', 'Jomar sent you a message.', 'message', 'apps-chat.php?user_id=4', 1, '2026-03-17 08:09:04', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ojt_edit_audit`
--

CREATE TABLE `ojt_edit_audit` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `editor_user_id` int(11) NOT NULL DEFAULT 0,
  `reason` varchar(500) NOT NULL,
  `changes_text` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ojt_reminder_queue`
--

CREATE TABLE `ojt_reminder_queue` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reminder_type` varchar(100) NOT NULL,
  `payload` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `queued_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ojt_reminder_queue`
--

INSERT INTO `ojt_reminder_queue` (`id`, `student_id`, `reminder_type`, `payload`, `status`, `queued_by`, `created_at`) VALUES
(1, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"No MOA\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:03'),
(2, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"No Endorsement\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:04'),
(3, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"No biometric logs 3+ days\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:04'),
(4, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"Low completion\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:04'),
(5, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"Pending attendance approvals\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:04'),
(6, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"No MOA\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:04'),
(7, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"No Endorsement\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:05'),
(8, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"No biometric logs 3+ days\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:05'),
(9, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"Low completion\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:05'),
(10, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"Pending attendance approvals\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:05'),
(11, 1, 'risk_flag', '{\"student_id\":1,\"student\":\"Felix Luis Mateo\",\"flag\":\"Low completion\",\"risk_score\":40}', 'pending', 4, '2026-03-12 09:52:05'),
(12, 1, 'risk_flag', '{\"student_id\":1,\"student\":\"Felix Luis Mateo\",\"flag\":\"Pending attendance approvals\",\"risk_score\":40}', 'pending', 4, '2026-03-12 09:52:05'),
(13, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"No MOA\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:05'),
(14, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"No Endorsement\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:05'),
(15, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"No biometric logs 3+ days\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:05'),
(16, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"Low completion\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:06'),
(17, 2, 'risk_flag', '{\"student_id\":2,\"student\":\"Ivan Sanchez\",\"flag\":\"Pending attendance approvals\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:06'),
(18, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"No MOA\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:06'),
(19, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"No Endorsement\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:06'),
(20, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"No biometric logs 3+ days\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:06'),
(21, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"Low completion\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:06'),
(22, 4, 'risk_flag', '{\"student_id\":4,\"student\":\"Von Ezekiel Lopez\",\"flag\":\"Pending attendance approvals\",\"risk_score\":100}', 'pending', 4, '2026-03-12 09:52:06'),
(23, 1, 'risk_flag', '{\"student_id\":1,\"student\":\"Felix Luis Mateo\",\"flag\":\"Low completion\",\"risk_score\":40}', 'pending', 4, '2026-03-12 09:52:06'),
(24, 1, 'risk_flag', '{\"student_id\":1,\"student\":\"Felix Luis Mateo\",\"flag\":\"Pending attendance approvals\",\"risk_score\":40}', 'pending', 4, '2026-03-12 09:52:06');

-- --------------------------------------------------------

--
-- Table structure for table `ojt_supervisor_reviews`
--

CREATE TABLE `ojt_supervisor_reviews` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reviewer_user_id` int(11) NOT NULL DEFAULT 0,
  `reviewer_role` varchar(50) NOT NULL DEFAULT '',
  `note` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `year` varchar(20) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `year`, `created_at`, `updated_at`) VALUES
(1, '2026-2027', '2026-03-09 09:57:45', '2026-03-09 09:57:45');

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

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `name`, `code`, `course_id`, `department_id`, `description`, `capacity`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '2A', 'ACT', 1, 1, NULL, NULL, 1, '2026-03-05 08:04:24', '2026-03-05 08:04:24', NULL),
(3, '2A', 'ACT2A', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(4, '2B', 'ACT2B', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(5, '2C', 'ACT2C', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(6, '2D', 'ACT2D', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(7, '2E', 'ACT2E', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(8, '2F', 'ACT2F', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(9, '2G', 'ACT2G', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(10, '2H', 'ACT2H', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(11, '2I', 'ACT2I', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(12, '2J', 'ACT2J', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(13, '2K', 'ACT2K', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(14, '2L', 'ACT2L', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(15, '2M', 'ACT2M', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(16, '2N', 'ACT2N', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(17, '2O', 'ACT2O', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(18, '2P', 'ACT2P', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(19, '2Q', 'ACT2Q', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(20, '2R', 'ACT2R', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(21, '2S', 'ACT2S', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(22, '2T', 'ACT2T', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(23, '2U', 'ACT2U', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(24, '2V', 'ACT2V', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(25, '2W', 'ACT2W', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(26, '2X', 'ACT2X', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(27, '2Y', 'ACT2Y', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(28, '2Z', 'ACT2Z', 1, 1, NULL, NULL, 1, '2026-03-17 07:41:31', '2026-03-17 07:41:31', NULL),
(29, '3A', 'HMT3A', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(30, '3B', 'HMT3B', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(31, '3C', 'HMT3C', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(32, '3D', 'HMT3D', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(33, '3E', 'HMT3E', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(34, '3F', 'HMT3F', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(35, '3G', 'HMT3G', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(36, '3H', 'HMT3H', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(37, '3I', 'HMT3I', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(38, '3J', 'HMT3J', 5, 1, NULL, NULL, 1, '2026-03-18 02:23:40', '2026-03-18 02:23:40', NULL),
(39, '3A', 'BSE3A', 6, 1, NULL, NULL, 1, '2026-03-18 02:23:56', '2026-03-18 02:23:56', NULL),
(40, '3B', 'BSE3B', 6, 1, NULL, NULL, 1, '2026-03-18 02:23:56', '2026-03-18 02:23:56', NULL),
(41, '3C', 'BSE3C', 6, 1, NULL, NULL, 1, '2026-03-18 02:23:56', '2026-03-18 02:23:56', NULL),
(42, '3D', 'BSE3D', 6, 1, NULL, NULL, 1, '2026-03-18 02:23:56', '2026-03-18 02:23:56', NULL),
(43, '3E', 'BSE3E', 6, 1, NULL, NULL, 1, '2026-03-18 02:23:56', '2026-03-18 02:23:56', NULL),
(44, '3F', 'BSE3F', 6, 1, NULL, NULL, 1, '2026-03-18 02:23:56', '2026-03-18 02:23:56', NULL),
(45, '3G', 'BSE3G', 6, 1, NULL, NULL, 1, '2026-03-18 02:23:56', '2026-03-18 02:23:56', NULL),
(46, '3A', 'BSOA3A', 2, 1, NULL, NULL, 1, '2026-03-18 02:24:12', '2026-03-18 02:24:12', NULL),
(47, '3B', 'BSOA3B', 2, 1, NULL, NULL, 1, '2026-03-18 02:24:12', '2026-03-18 02:24:12', NULL),
(48, '3C', 'BSOA3C', 2, 1, NULL, NULL, 1, '2026-03-18 02:24:12', '2026-03-18 02:24:12', NULL),
(49, '3D', 'BSOA3D', 2, 1, NULL, NULL, 1, '2026-03-18 02:24:12', '2026-03-18 02:24:12', NULL),
(50, '3E', 'BSOA3E', 2, 1, NULL, NULL, 1, '2026-03-18 02:24:12', '2026-03-18 02:24:12', NULL),
(51, '3F', 'BSOA3F', 2, 1, NULL, NULL, 1, '2026-03-18 02:24:12', '2026-03-18 02:24:12', NULL),
(52, '2A', 'CT2A', 7, 1, NULL, NULL, 1, '2026-03-18 02:24:31', '2026-03-18 02:24:31', NULL);

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
  `bio` varchar(255) NOT NULL,
  `department_id` varchar(255) NOT NULL,
  `section_id` int(11) NOT NULL,
  `supervisor_name` text DEFAULT NULL,
  `coordinator_name` text DEFAULT NULL,
  `supervisor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `coordinator_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `internal_total_hours_remaining` int(11) NOT NULL,
  `internal_total_hours` int(11) NOT NULL,
  `external_total_hours_remaining` int(11) NOT NULL,
  `external_total_hours` int(11) NOT NULL,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `biometric_registered` tinyint(1) NOT NULL DEFAULT 0,
  `biometric_registered_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT '1',
  `school_year` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `assignment_track` varchar(20) NOT NULL DEFAULT 'internal',
  `emergency_contact_phone` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `course_id`, `student_id`, `first_name`, `last_name`, `middle_name`, `username`, `password`, `email`, `bio`, `department_id`, `section_id`, `supervisor_name`, `coordinator_name`, `supervisor_id`, `coordinator_id`, `phone`, `date_of_birth`, `gender`, `address`, `internal_total_hours_remaining`, `internal_total_hours`, `external_total_hours_remaining`, `external_total_hours`, `emergency_contact`, `profile_picture`, `biometric_registered`, `biometric_registered_at`, `status`, `school_year`, `created_at`, `updated_at`, `deleted_at`, `assignment_track`, `emergency_contact_phone`) VALUES
(1, 3, 1, '05-8531', 'Felix Luis', 'Mateo', 'Manaloto', 'FelixLuisMateo', '$2y$10$A2YrsP4KvtdM5noZZxOoge4X/48YkAbi7lxDIEb9ACgbO1PqYlDXK', 'mateo.felixluis@gmail.com', 'sadfH', '1', 0, 'Julius Gomez', 'Mark Verzon', 5, 6, '09091783340', '2006-03-16', 'male', '6553 Balaba 2, Dau, Mabalacat City, Pampanga', 117, 140, 0, 0, 'Carlota Manaloto (09385754436)', 'uploads/profile_pictures/student_1_1771902320.png', 0, NULL, '1', '2025-2026', '2026-02-24 02:57:53', '2026-03-17 07:44:30', NULL, 'internal', NULL),
(2, 5, 1, '05-8377', 'Ivan', 'Sanchez', 'Umali', 'IvanSanchez', '$2y$10$G4AcrvLY6tYayygIQhJIfeQNievmy1uMNCLr7eTSi.f/9Ibibu1AW', 'IvanSanchez@gmail.com', '', '1', 0, 'Julius Gomez', 'Mark Verzon', NULL, 6, '09774911238', '2005-07-02', 'male', 'Madadap Mabalacat City, Pampanga', 138, 140, 0, 0, 'Irma U. Sanchez (09511422631)', 'uploads/profile_pictures/student_2_1772087105.jpg', 0, NULL, '0', '2025-2026', '2026-02-26 06:22:05', '2026-03-11 08:23:33', NULL, 'internal', NULL),
(4, 7, 1, '05-9430', 'Von Ezekiel', 'Lopez', '', 'VonLopez', '$2y$10$ayidEKj38t78PHsPmzISvuEQpxeJYThqMra5yYHwemG8BvhPl6QSy', 'vonlopez@gmail.com', '', '1', 1, 'Julius Gomez', 'Mark Verzon', 5, 6, '09936202254', '2026-09-02', 'male', 'Pineda Subd, Ilang Ilang St, Dau, Mabalacat City, Pampanga', 1, 1, 0, 0, 'Ruby Lopez (09911446820)', NULL, 0, NULL, '0', '2025-2026', '2026-03-05 08:04:24', '2026-03-11 08:23:33', NULL, 'internal', NULL),
(5, 8, 1, '05-12346', 'Naven', 'Cuenca', 'Mercado', 'NavenCuenca', '$2y$10$YloO960aow.5YHdreGaH6eOGLY2JMcUgTWHwTLjX1THwX9W8x.WfK', 'Naven@gmail.com', '', '1', 1, NULL, NULL, NULL, NULL, '09123479845', '2005-11-20', 'male', 'Duquit, Dau, Mabalacat City, Pampanga', 140, 140, 0, 250, 'Lani Mercado (09478945879)', NULL, 0, NULL, '1', '2025-2026', '2026-03-10 00:33:51', '2026-03-11 08:23:33', NULL, 'internal', NULL),
(6, 9, 1, '05-8969', 'Jomer', 'De Guzman', 'Rivera', 'JomerDeGuzman', '$2y$10$MWF9iNupm327Aoj0X0/R5.i9ehi9E7e7LBbxYyVnO5qwCYbWKTqwG', 'JomerDeGuzman@gmail.com', '', '1', 1, 'Julius Gomez', 'Mark Verzon', 5, 6, '09147897451', '0000-00-00', 'male', '14th Street, Dapdap, Mabalacat City, Pampanga', 140, 140, 0, 250, 'Mama De Guzman (09784581268)', NULL, 0, NULL, '1', '2025-2026', '2026-03-10 07:18:34', '2026-03-11 08:23:33', NULL, 'internal', NULL),
(7, 10, 1, '05-7262', 'Lucky', 'Mateo', 'Manaloto', 'Lucky', '$2y$10$SEt5Ou0nZuwXnc2PhHWuG.3XnrUUyzxujnjePuU7hliVNcZzlS3sm', 'luckyluckymateo@gmail.com', '', '1', 1, '0', 'Mark Verzon', 5, 6, '09204098957', '2006-03-16', 'male', '6553 Balaba 2, Dau, Mabalacat City, Pampanga', 140, 140, 0, 250, 'Carlota Mateo (09652062364)', NULL, 0, NULL, '1', '2025-2026', '2026-03-14 11:57:21', '2026-03-14 11:58:15', NULL, 'internal', NULL),
(8, 12, 1, '05-0702', 'Ivan', '123', 'Pogi', 'ivan', '$2y$10$7TQ3079wW9NkzEi.hTiy6et9gMngPkSLQcc5RMcwXDv7.gNx0b8U6', 'work.ivansanchez@gmail.com', '', '1', 1, 'Julius Gomez', 'Mark Verzon', 5, 6, '09774911238', '2005-07-02', 'male', 'bahay', 0, 140, 250, 250, '09123456789 (09123456789)', '', 0, NULL, '0', '2025-2026', '2026-03-17 07:36:19', '2026-03-17 07:43:52', NULL, 'external', NULL);

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
`id` bigint(20) unsigned
,`student_id` varchar(255)
,`full_name` text
,`email` varchar(255)
,`phone` varchar(255)
,`date_of_birth` date
,`gender` enum('male','female','other')
,`address` varchar(255)
,`emergency_contact` varchar(255)
,`is_active` int(1)
,`course` varchar(255)
,`course_code` varchar(255)
,`user_name` varchar(255)
,`user_role` enum('admin','coordinator','supervisor','student')
,`internship_id` bigint(20) unsigned
,`internship_type` enum('internal','external')
,`company_name` varchar(255)
,`position` varchar(255)
,`start_date` date
,`end_date` date
,`internship_status` enum('pending','ongoing','completed','cancelled')
,`required_hours` int(11)
,`rendered_hours` int(11)
,`completion_percentage` decimal(5,2)
,`coordinator_name` varchar(255)
,`supervisor_name` varchar(255)
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
(5, 1, 'Julius', 'Gomez', NULL, 'juluisgomez@gmail.com', '09761551465', 1, 'Comlab 2', NULL, NULL, 1, '2026-02-24 02:53:39', '2026-03-02 01:19:42', NULL);

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
  `profile_picture` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `application_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `application_submitted_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `approval_notes` varchar(255) DEFAULT NULL,
  `disciplinary_remark` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `email_verified_at`, `password`, `remember_token`, `role`, `is_active`, `profile_picture`, `created_at`, `updated_at`, `application_status`, `application_submitted_at`, `approved_by`, `approved_at`, `rejected_at`, `approval_notes`, `disciplinary_remark`) VALUES
(1, 'JuliusGomez', 'JuliusGomez', 'juluisgomez@gmail.com', NULL, '$2y$10$O3c1pTPq4RNJgOdu22ltdO100ICm7slNH7ZsLmMHCY/ZdglUTu/ya', NULL, 'supervisor', 1, '', '2026-02-24 02:53:39', '2026-02-24 02:53:39', 'approved', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'MarkVerzon', 'MarkVerzon', 'markverzon@gmail.com', NULL, '$2y$10$TdhRjBcTSXzliYCnZyeKbeo35UWgShc3fX7d4Z7WhVl9coGXHxGJ2', NULL, 'coordinator', 1, '', '2026-02-24 02:56:30', '2026-03-03 08:42:36', 'approved', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Felix Luis Mateo', 'FelixLuisMateo', 'mateo.felixluis@gmail.com', NULL, '$2y$10$PQtM4YQ0bmhzE8FFT.3CRu1Iuq.tDGQSlK6iQv.Hi/qX4hMhCXKu.', NULL, 'student', 1, '', '2026-02-24 02:57:53', '2026-02-26 07:38:43', 'approved', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Jomar', 'Jomar', 'jomarsangil@gmail.com', NULL, '$2y$10$iE4WlTO6Ny3zNcC.v.1Tae04qQtUxcC2f.h9/v7VuKj7q3a4145HO', NULL, 'admin', 1, '', '2026-02-24 03:14:42', '2026-02-24 03:14:42', 'approved', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Ivan Sanchez', 'IvanSanchez', 'IvanSanchez@gmail.com', NULL, '$2y$10$G4AcrvLY6tYayygIQhJIfeQNievmy1uMNCLr7eTSi.f/9Ibibu1AW', NULL, 'student', 1, '', '2026-02-26 06:22:05', '2026-03-03 05:06:17', 'approved', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Tyron Jay Gonzales', 'TyronGonzales', 'Tyron@gmail.com', NULL, '$2y$10$p6uQoFaN6mppBsGGH9iDJOUKVPChh9Oi2T93gUkRFCr/AhAlyLpm2', NULL, 'student', 1, '', '2026-02-26 06:44:27', '2026-03-11 00:20:35', 'approved', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Von Ezekiel Lopez', 'VonLopez', 'vonlopez@gmail.com', NULL, '$2y$10$ayidEKj38t78PHsPmzISvuEQpxeJYThqMra5yYHwemG8BvhPl6QSy', NULL, 'student', 1, '', '2026-03-05 08:04:24', '2026-03-05 08:04:24', 'approved', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'Naven Cuenca', 'NavenCuenca', 'Naven@gmail.com', NULL, '$2y$10$YloO960aow.5YHdreGaH6eOGLY2JMcUgTWHwTLjX1THwX9W8x.WfK', NULL, 'student', 1, '', '2026-03-10 00:33:51', '2026-03-10 00:33:51', 'pending', '2026-03-10 08:33:51', NULL, NULL, NULL, NULL, NULL),
(9, 'Jomer De Guzman', 'JomerDeGuzman', 'JomerDeGuzman@gmail.com', NULL, '$2y$10$MWF9iNupm327Aoj0X0/R5.i9ehi9E7e7LBbxYyVnO5qwCYbWKTqwG', NULL, 'student', 1, '', '2026-03-10 07:18:34', '2026-03-10 07:18:34', 'pending', '2026-03-10 15:18:34', NULL, NULL, NULL, NULL, NULL),
(10, 'Lucky Mateo', 'Lucky', 'luckyluckymateo@gmail.com', NULL, '$2y$10$SEt5Ou0nZuwXnc2PhHWuG.3XnrUUyzxujnjePuU7hliVNcZzlS3sm', NULL, 'admin', 1, '', '2026-03-14 11:57:21', '2026-03-17 05:45:45', 'approved', '2026-03-14 19:57:21', 4, '2026-03-14 19:58:15', NULL, '', ''),
(12, 'Ivan 123', 'ivan', 'work.ivansanchez@gmail.com', NULL, '$2y$10$N8qGbH2Y0H9pe8oge6wGQ./8deR0/yVJtlhQurNH3wwvFL5VoAFyy', NULL, 'admin', 1, '', '2026-03-17 07:36:19', '2026-03-17 07:45:28', 'approved', '2026-03-17 15:36:19', 4, '2026-03-17 15:40:35', NULL, '', '');

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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_profile_with_internship`  AS SELECT `s`.`id` AS `id`, `s`.`student_id` AS `student_id`, concat(`s`.`first_name`,' ',coalesce(`s`.`middle_name`,''),' ',`s`.`last_name`) AS `full_name`, `s`.`email` AS `email`, `s`.`phone` AS `phone`, `s`.`date_of_birth` AS `date_of_birth`, `s`.`gender` AS `gender`, `s`.`address` AS `address`, `s`.`emergency_contact` AS `emergency_contact`, CASE WHEN `s`.`status` in ('1','active') THEN 1 ELSE 0 END AS `is_active`, `c`.`name` AS `course`, `c`.`code` AS `course_code`, `u`.`name` AS `user_name`, `u`.`role` AS `user_role`, `i`.`id` AS `internship_id`, `i`.`type` AS `internship_type`, `i`.`company_name` AS `company_name`, `i`.`position` AS `position`, `i`.`start_date` AS `start_date`, `i`.`end_date` AS `end_date`, `i`.`status` AS `internship_status`, `i`.`required_hours` AS `required_hours`, `i`.`rendered_hours` AS `rendered_hours`, `i`.`completion_percentage` AS `completion_percentage`, `coord`.`name` AS `coordinator_name`, `sup`.`name` AS `supervisor_name` FROM (((((`students` `s` left join `courses` `c` on(`s`.`course_id` = `c`.`id`)) left join `users` `u` on(`s`.`user_id` = `u`.`id`)) left join `internships` `i` on(`s`.`id` = `i`.`student_id`)) left join `users` `coord` on(`i`.`coordinator_id` = `coord`.`id`)) left join `users` `sup` on(`i`.`supervisor_id` = `sup`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `application_letter`
--
ALTER TABLE `application_letter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

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
-- Indexes for table `coordinator_courses`
--
ALTER TABLE `coordinator_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coord_course` (`coordinator_user_id`,`course_id`),
  ADD KEY `idx_coord_user` (`coordinator_user_id`),
  ADD KEY `idx_coord_course` (`course_id`);

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
-- Indexes for table `dau_moa`
--
ALTER TABLE `dau_moa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

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
-- Indexes for table `document_workflow`
--
ALTER TABLE `document_workflow`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_doc` (`user_id`,`doc_type`);

--
-- Indexes for table `endorsement`
--
ALTER TABLE `endorsement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `endorsement_letter`
--
ALTER TABLE `endorsement_letter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluations_student_id_foreign` (`student_id`);

--
-- Indexes for table `evaluation_unlocks`
--
ALTER TABLE `evaluation_unlocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student` (`student_id`);

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
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_logs_user_id` (`user_id`),
  ADD KEY `idx_login_logs_status_created` (`status`,`created_at`);

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
-- Indexes for table `message_pins`
--
ALTER TABLE `message_pins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_message_pin` (`message_id`),
  ADD KEY `idx_pinned_by_user` (`pinned_by_user_id`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_message_user` (`message_id`,`user_id`),
  ADD KEY `idx_message_emoji` (`message_id`,`emoji`),
  ADD KEY `idx_reaction_user` (`user_id`);

--
-- Indexes for table `message_reports`
--
ALTER TABLE `message_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reporter_message` (`message_id`,`reporter_user_id`),
  ADD KEY `idx_reported_user` (`reported_user_id`),
  ADD KEY `idx_report_status` (`status`),
  ADD KEY `idx_report_reviewed_at` (`reviewed_at`),
  ADD KEY `idx_report_created` (`created_at`);

--
-- Indexes for table `moa`
--
ALTER TABLE `moa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_user_id_foreign` (`user_id`);

--
-- Indexes for table `ojt_edit_audit`
--
ALTER TABLE `ojt_edit_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `ojt_reminder_queue`
--
ALTER TABLE `ojt_reminder_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `ojt_supervisor_reviews`
--
ALTER TABLE `ojt_supervisor_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_school_year` (`year`);

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
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_letter`
--
ALTER TABLE `application_letter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `coordinator_courses`
--
ALTER TABLE `coordinator_courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `daily_time_records`
--
ALTER TABLE `daily_time_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dau_moa`
--
ALTER TABLE `dau_moa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- AUTO_INCREMENT for table `document_workflow`
--
ALTER TABLE `document_workflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `endorsement`
--
ALTER TABLE `endorsement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `endorsement_letter`
--
ALTER TABLE `endorsement_letter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluation_unlocks`
--
ALTER TABLE `evaluation_unlocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hour_logs`
--
ALTER TABLE `hour_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `internships`
--
ALTER TABLE `internships`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `manual_dtr_attachments`
--
ALTER TABLE `manual_dtr_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `message_pins`
--
ALTER TABLE `message_pins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `message_reports`
--
ALTER TABLE `message_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `moa`
--
ALTER TABLE `moa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `ojt_edit_audit`
--
ALTER TABLE `ojt_edit_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ojt_reminder_queue`
--
ALTER TABLE `ojt_reminder_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `ojt_supervisor_reviews`
--
ALTER TABLE `ojt_supervisor_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
-- Constraints for table `coordinator_courses`
--
ALTER TABLE `coordinator_courses`
  ADD CONSTRAINT `fk_coord_courses_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_coord_courses_user` FOREIGN KEY (`coordinator_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
