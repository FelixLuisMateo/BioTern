-- MariaDB dump 10.19  Distrib 10.4.27-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: biotern_db
-- ------------------------------------------------------
-- Server version	10.4.27-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `biotern_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `biotern_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `biotern_db`;

--
-- Table structure for table `admin`
--

DROP TABLE `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_letter`
--

DROP TABLE `application_letter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_letter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `application_person` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_letter`
--

LOCK TABLES `application_letter` WRITE;
/*!40000 ALTER TABLE `application_letter` DISABLE KEYS */;
INSERT INTO `application_letter` VALUES (12,1,'2026-02-26','Ivan Sanchez','Human Resources','Biotern','Aurea St. Samsonville, Dau, Mabalacat City, Pampanga'),(19,2,'2026-03-05','Johnny Sins','69420','Brazzer','xhamster');
/*!40000 ALTER TABLE `application_letter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendances`
--

DROP TABLE `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `internship_id` bigint(20) unsigned DEFAULT NULL,
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
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `rejection_remarks` text DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_attendance_date_status` (`attendance_date`,`status`),
  KEY `idx_approved_by` (`approved_by`),
  KEY `idx_internship_attendance` (`internship_id`,`attendance_date`),
  KEY `idx_student_attendance` (`student_id`,`attendance_date`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
INSERT INTO `attendances` VALUES (1,1,NULL,'2026-02-24','11:17:00','11:17:00','11:18:00','11:18:00','11:18:00','11:18:00',0.00,'manual','pending',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-24 03:17:45','2026-02-24 03:18:22'),(2,1,NULL,'2026-02-26',NULL,NULL,NULL,NULL,'11:01:00','16:26:00',5.42,'manual','pending',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 03:01:08','2026-02-26 08:26:30'),(3,2,NULL,'2026-02-26','16:28:00',NULL,NULL,NULL,'14:25:00','16:25:00',2.00,'manual','pending',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-26 06:25:25','2026-02-26 08:28:05'),(4,1,NULL,'2026-02-27',NULL,NULL,NULL,NULL,'14:23:00','18:57:00',4.57,'manual','pending',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-27 06:23:04','2026-02-27 10:57:23'),(5,1,NULL,'2026-02-28','08:08:00',NULL,NULL,NULL,NULL,NULL,NULL,'manual','pending',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-28 00:08:52','2026-02-28 00:08:52'),(6,1,NULL,'2026-03-02','08:39:00',NULL,NULL,NULL,NULL,NULL,NULL,'manual','pending',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-02 00:39:25','2026-03-02 00:39:25');
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `biometric_data`
--

DROP TABLE `biometric_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `biometric_data` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `biometric_type` enum('fingerprint','face','iris') NOT NULL,
  `template` longblob DEFAULT NULL,
  `registered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `biometric_data_student_id_foreign` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `biometric_data`
--

LOCK TABLES `biometric_data` WRITE;
/*!40000 ALTER TABLE `biometric_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `biometric_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `certificate_type` varchar(255) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `certificate_number` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_number` (`certificate_number`),
  KEY `certificates_student_id_foreign` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coordinators`
--

DROP TABLE `coordinators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coordinators` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `office_location` varchar(255) DEFAULT NULL,
  `bio` longtext DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_department_id` (`department_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coordinators`
--

LOCK TABLES `coordinators` WRITE;
/*!40000 ALTER TABLE `coordinators` DISABLE KEYS */;
INSERT INTO `coordinators` VALUES (6,2,'Mark','Verzon',NULL,'markverzon@gmail.com','9091734512',1,'IT Faculty',NULL,NULL,1,'2026-02-24 02:56:30','2026-02-24 02:56:30',NULL),(7,8,'Ivan Jakol','video',NULL,'GoldniWally@gmail.com','45345643435',1,'Summerville',NULL,NULL,1,'2026-03-05 07:53:02','2026-03-11 05:12:14','2026-03-11 05:12:14');
/*!40000 ALTER TABLE `coordinators` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `course_head` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'Associate in Computer Technology','ACT','Jomar G. Sangil','2026-02-24 01:31:25','2026-02-24 01:31:25',NULL),(2,'Bachelor of Science in Office Administration','BSOA','Juan Dela Cruz','2026-02-24 02:51:14','2026-02-26 06:14:16',NULL),(5,'Hospitality Management and Technology','HMT','Prof. Pakmey','2026-03-05 08:18:07','2026-03-05 08:18:07',NULL),(6,'Computer Technology','CT','Prof. Pedro Penduko','2026-03-05 08:18:47','2026-03-05 08:18:47',NULL),(7,'Bachelor of Science in Entrepreneurship','BSE','Prof. Vina Vich','2026-03-05 08:20:06','2026-03-05 08:20:32',NULL);
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_time_records`
--

DROP TABLE `daily_time_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_time_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `hours_worked` decimal(5,2) NOT NULL DEFAULT 0.00,
  `remarks` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`),
  KEY `daily_time_records_student_id_foreign` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_time_records`
--

LOCK TABLES `daily_time_records` WRITE;
/*!40000 ALTER TABLE `daily_time_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_time_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dau_moa`
--

DROP TABLE `dau_moa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dau_moa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dau_moa`
--

LOCK TABLES `dau_moa` WRITE;
/*!40000 ALTER TABLE `dau_moa` DISABLE KEYS */;
INSERT INTO `dau_moa` VALUES (1,1,'BIODERM','','','','','','','','','','','','','','','','','','','','','','','','2026-02-27 15:02:54','2026-02-27 15:31:07');
/*!40000 ALTER TABLE `dau_moa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `department_head` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Information Technology','DEPT-IT','Jomar G. Sangil','jomar.sangil@clarkcollege.edu.ph','2026-02-23 10:08:58','2026-02-26 06:11:44',NULL),(4,'Bachelor of Science in Entrepreneurship','BSE','Prof. Juanna Doz','juannadoz@gmail.com','2026-03-05 08:11:11','2026-03-05 08:11:11',NULL),(5,'Hospitality Management and Technology','HTM','Prof. Suk Madik','sukmadik@gmail.com','2026-03-05 08:12:19','2026-03-05 08:12:19',NULL),(6,'Bachelor of Science in Office Administration','BSOA','Prof. Ice Wallowcome','Icewallowcome@gmail.com','2026-03-05 08:13:31','2026-03-05 08:13:31',NULL);
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_workflow`
--

DROP TABLE `document_workflow`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_workflow` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `doc_type` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `review_notes` text DEFAULT NULL,
  `approved_by` int(11) NOT NULL DEFAULT 0,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_doc` (`user_id`,`doc_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_workflow`
--

LOCK TABLES `document_workflow` WRITE;
/*!40000 ALTER TABLE `document_workflow` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_workflow` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
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
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `last_modified_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_student_id_foreign` (`student_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `endorsement`
--

DROP TABLE `endorsement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `endorsement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `endorsement`
--

LOCK TABLES `endorsement` WRITE;
/*!40000 ALTER TABLE `endorsement` DISABLE KEYS */;
/*!40000 ALTER TABLE `endorsement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `endorsement_letter`
--

DROP TABLE `endorsement_letter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `endorsement_letter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_title` varchar(20) DEFAULT 'none',
  `recipient_position` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `students_to_endorse` text DEFAULT NULL,
  `greeting_preference` varchar(20) DEFAULT 'either',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `endorsement_letter`
--

LOCK TABLES `endorsement_letter` WRITE;
/*!40000 ALTER TABLE `endorsement_letter` DISABLE KEYS */;
INSERT INTO `endorsement_letter` VALUES (1,1,'dddd','none','','','','','either','2026-02-27 15:11:54','2026-02-27 15:30:57'),(3,2,'gg','auto','','','','','either','2026-03-09 11:24:17','2026-03-09 11:24:17');
/*!40000 ALTER TABLE `endorsement_letter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_unlocks`
--

DROP TABLE `evaluation_unlocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_unlocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `internship_id` int(11) DEFAULT NULL,
  `is_unlocked` tinyint(1) NOT NULL DEFAULT 0,
  `unlocked_at` datetime DEFAULT NULL,
  `unlocked_by` int(11) DEFAULT NULL,
  `unlock_source` varchar(30) NOT NULL DEFAULT 'manual',
  `unlock_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_unlocks`
--

LOCK TABLES `evaluation_unlocks` WRITE;
/*!40000 ALTER TABLE `evaluation_unlocks` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluation_unlocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluations`
--

DROP TABLE `evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `evaluator_name` varchar(255) DEFAULT NULL,
  `evaluation_date` date NOT NULL,
  `score` int(11) DEFAULT NULL,
  `feedback` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `evaluations_student_id_foreign` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluations`
--

LOCK TABLES `evaluations` WRITE;
/*!40000 ALTER TABLE `evaluations` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hour_logs`
--

DROP TABLE `hour_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hour_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `date` date NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hour_logs_student_id_foreign` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hour_logs`
--

LOCK TABLES `hour_logs` WRITE;
/*!40000 ALTER TABLE `hour_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `hour_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internships`
--

DROP TABLE `internships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `department_id` bigint(20) unsigned NOT NULL,
  `coordinator_id` bigint(20) unsigned NOT NULL,
  `supervisor_id` bigint(20) unsigned DEFAULT NULL,
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_department_id` (`department_id`),
  KEY `idx_coordinator_id` (`coordinator_id`),
  KEY `idx_supervisor_id` (`supervisor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internships`
--

LOCK TABLES `internships` WRITE;
/*!40000 ALTER TABLE `internships` DISABLE KEYS */;
INSERT INTO `internships` VALUES (7,1,1,1,2,1,'internal',NULL,NULL,NULL,'2026-02-24',NULL,NULL,'ongoing','2026-2027',140,10,7.14,'2026-02-24 03:05:20','2026-02-27 10:57:23',NULL),(8,2,1,1,2,1,'internal',NULL,NULL,NULL,'2026-02-26',NULL,NULL,'ongoing','2026-2027',140,2,1.43,'2026-02-26 06:25:05','2026-03-11 08:36:01',NULL);
/*!40000 ALTER TABLE `internships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_logs`
--

DROP TABLE `login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `identifier` varchar(191) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_logs_user_id` (`user_id`),
  KEY `idx_login_logs_status_created` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_logs`
--

LOCK TABLES `login_logs` WRITE;
/*!40000 ALTER TABLE `login_logs` DISABLE KEYS */;
INSERT INTO `login_logs` VALUES (1,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-10 09:13:52'),(2,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-10 09:18:34'),(3,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-10 09:43:50'),(4,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-10 17:37:11'),(5,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 12:24:51'),(6,NULL,'ivan','','failed','invalid_credentials','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 12:38:17'),(7,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 12:38:51'),(8,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 12:51:01'),(9,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 12:53:56'),(10,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 13:03:17'),(11,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 13:11:27'),(12,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 13:13:35'),(13,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 13:21:31'),(14,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 13:26:15'),(15,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 13:40:21'),(16,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 14:44:55'),(17,7,'Testo','admin','success','login_success','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 OPR/127.0.0.0','2026-03-11 18:13:04');
/*!40000 ALTER TABLE `login_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `manual_dtr_attachments`
--

DROP TABLE `manual_dtr_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manual_dtr_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `attendance_id` bigint(20) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL COMMENT 'e.g., Biometric Machine Breakdown',
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_id` (`attendance_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_attendance_date` (`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `manual_dtr_attachments`
--

LOCK TABLES `manual_dtr_attachments` WRITE;
/*!40000 ALTER TABLE `manual_dtr_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `manual_dtr_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_user_id` bigint(20) unsigned NOT NULL,
  `to_user_id` bigint(20) unsigned NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` longtext NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_from_user_id_foreign` (`from_user_id`),
  KEY `messages_to_user_id_foreign` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moa`
--

DROP TABLE `moa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `moa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `acknowledgement_address` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moa`
--

LOCK TABLES `moa` WRITE;
/*!40000 ALTER TABLE `moa` DISABLE KEYS */;
/*!40000 ALTER TABLE `moa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` longtext DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ojt_edit_audit`
--

DROP TABLE `ojt_edit_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ojt_edit_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `editor_user_id` int(11) NOT NULL DEFAULT 0,
  `reason` varchar(500) NOT NULL,
  `changes_text` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ojt_edit_audit`
--

LOCK TABLES `ojt_edit_audit` WRITE;
/*!40000 ALTER TABLE `ojt_edit_audit` DISABLE KEYS */;
/*!40000 ALTER TABLE `ojt_edit_audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ojt_reminder_queue`
--

DROP TABLE `ojt_reminder_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ojt_reminder_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `reminder_type` varchar(100) NOT NULL,
  `payload` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `queued_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ojt_reminder_queue`
--

LOCK TABLES `ojt_reminder_queue` WRITE;
/*!40000 ALTER TABLE `ojt_reminder_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `ojt_reminder_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ojt_supervisor_reviews`
--

DROP TABLE `ojt_supervisor_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ojt_supervisor_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `reviewer_user_id` int(11) NOT NULL DEFAULT 0,
  `reviewer_role` varchar(50) NOT NULL DEFAULT '',
  `note` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ojt_supervisor_reviews`
--

LOCK TABLES `ojt_supervisor_reviews` WRITE;
/*!40000 ALTER TABLE `ojt_supervisor_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `ojt_supervisor_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sections`
--

DROP TABLE `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
  `department_id` bigint(20) unsigned NOT NULL,
  `description` longtext DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sections_code_name_unique` (`code`,`name`),
  KEY `course_id` (`course_id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sections`
--

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
INSERT INTO `sections` VALUES (1,'2A','ACT',1,4,NULL,NULL,1,'2026-03-05 09:04:17','2026-03-05 09:04:17',NULL),(2,'2B','ACT',1,4,NULL,NULL,1,'2026-03-05 09:04:17','2026-03-05 09:04:17',NULL),(3,'2C','ACT',1,4,NULL,NULL,1,'2026-03-05 09:04:18','2026-03-05 09:04:18',NULL),(4,'2D','ACT',1,4,NULL,NULL,1,'2026-03-05 09:04:18','2026-03-05 09:04:18',NULL),(5,'2A','CT',6,4,NULL,NULL,1,'2026-03-05 09:14:12','2026-03-05 09:14:12',NULL),(6,'2B','CT',6,4,NULL,NULL,1,'2026-03-05 09:14:12','2026-03-05 09:14:12',NULL),(7,'2C','CT',6,4,NULL,NULL,1,'2026-03-05 09:14:12','2026-03-05 09:14:12',NULL),(8,'2D','CT',6,4,NULL,NULL,1,'2026-03-05 09:14:12','2026-03-05 09:14:12',NULL),(9,'3A','BSE',7,4,NULL,NULL,1,'2026-03-05 10:03:53','2026-03-05 10:03:53',NULL),(10,'3B','BSE',7,4,NULL,NULL,1,'2026-03-05 10:03:53','2026-03-05 10:03:53',NULL),(11,'3C','BSE',7,4,NULL,NULL,1,'2026-03-05 10:03:53','2026-03-05 10:03:53',NULL),(12,'3D','BSE',7,4,NULL,NULL,1,'2026-03-05 10:03:53','2026-03-05 10:03:53',NULL),(13,'3A','BSOA',2,4,NULL,NULL,1,'2026-03-05 10:04:29','2026-03-05 10:04:29',NULL),(14,'3B','BSOA',2,4,NULL,NULL,1,'2026-03-05 10:04:29','2026-03-05 10:04:29',NULL),(15,'3C','BSOA',2,4,NULL,NULL,1,'2026-03-05 10:04:30','2026-03-05 10:04:30',NULL),(16,'3D','BSOA',2,4,NULL,NULL,1,'2026-03-05 10:04:30','2026-03-05 10:04:30',NULL),(17,'3A','HMT',5,4,NULL,NULL,1,'2026-03-05 10:05:36','2026-03-05 10:05:36',NULL),(18,'3B','HMT',5,4,NULL,NULL,1,'2026-03-05 10:05:36','2026-03-05 10:05:36',NULL),(19,'3C','HMT',5,4,NULL,NULL,1,'2026-03-05 10:05:36','2026-03-05 10:05:36',NULL),(20,'3D','HMT',5,4,NULL,NULL,1,'2026-03-05 10:05:36','2026-03-05 10:05:36',NULL);
/*!40000 ALTER TABLE `sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `course_id` bigint(20) unsigned NOT NULL,
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
  `supervisor_id` bigint(20) unsigned DEFAULT NULL,
  `coordinator_id` bigint(20) unsigned DEFAULT NULL,
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `assignment_track` varchar(20) NOT NULL DEFAULT 'internal',
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,3,1,'05-8531','Felix Luis','Mateo','Manaloto','FelixLuisMateo','$2y$10$A2YrsP4KvtdM5noZZxOoge4X/48YkAbi7lxDIEb9ACgbO1PqYlDXK','mateo.felixluis@gmail.com','sadfH','1',0,'Julius Gomez','Mark Verzon',5,6,'09091783340','2006-03-16','male','6553 Balaba 2, Dau, Mabalacat City, Pampanga',130,140,0,0,'Carlota Manaloto (09385754436)','uploads/profile_pictures/student_1_1771902320.png',0,NULL,'0','2026-02-24 02:57:53','2026-03-05 10:21:43',NULL,'internal',NULL),(2,5,1,'05-8377','Ivan','Sanchez','Umali','IvanSanchez','$2y$10$G4AcrvLY6tYayygIQhJIfeQNievmy1uMNCLr7eTSi.f/9Ibibu1AW','IvanSanchez@gmail.com','','1',3,'Julius Gomez','Mark Verzon',NULL,6,'09774911238','2005-07-02','male','Madadap Mabalacat City, Pampanga',138,140,0,0,'Irma U. Sanchez (09511422631)','uploads/profile_pictures/student_2_1773217993.jpg',0,NULL,'0','2026-02-26 06:22:05','2026-03-11 08:36:01',NULL,'internal',NULL),(4,13,7,'06-2343','Heavn','Sanchezss','Umalis','GoldniWallys','$2y$10$YAf4XDHNxusAgGgXRdNHw.553D83okI3rDUkWrPvO9FwVJOSUoE6y','GolsdniWally@gmail.com','','4',9,NULL,NULL,NULL,NULL,'097749112382','2005-06-16','male','sogo hotelss',140,140,0,250,'Irma sU. Sanchez (09511422631) (54534354343)',NULL,0,NULL,'1','2026-03-11 05:25:58','2026-03-11 05:25:58',NULL,'internal',NULL),(5,14,1,'05-2334','Heavns','Sanchez','Umali','GoldniWallys','$2y$10$deVOQRpdfWMbVDm/Sh84q.QFrqb.Agg7jL6/O4fTmurjDXAD56XKa','GoldniWally@gmail.coms','','4',1,NULL,NULL,NULL,NULL,'045345643435','2001-07-13','male','sogo hotelss',140,140,0,250,'Irma U. Sanchez (095114s22631) (54534354342)',NULL,0,NULL,'1','2026-03-11 05:40:13','2026-03-11 05:40:13',NULL,'internal',NULL);
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supervisors`
--

DROP TABLE `supervisors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supervisors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `office` varchar(255) DEFAULT NULL,
  `bio` longtext DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_department_id` (`department_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supervisors`
--

LOCK TABLES `supervisors` WRITE;
/*!40000 ALTER TABLE `supervisors` DISABLE KEYS */;
INSERT INTO `supervisors` VALUES (5,1,'Julius','Gomez',NULL,'juluisgomez@gmail.com','09761551465',1,'Comlab 2',NULL,NULL,1,'2026-02-24 02:53:39','2026-03-02 01:19:42',NULL),(7,0,'Oint','tesno','','oistem@gmail.com','097749112382',1,'Computer','','',1,'2026-03-12 00:44:35','2026-03-12 00:45:02','2026-03-12 00:45:02');
/*!40000 ALTER TABLE `supervisors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  UNIQUE KEY `system_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `upload_settings`
--

DROP TABLE `upload_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `upload_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `upload_type` varchar(50) NOT NULL COMMENT 'profile_picture, manual_dtr, documents',
  `base_path` varchar(255) NOT NULL COMMENT 'Path relative to web root',
  `max_file_size` bigint(20) DEFAULT 5242880 COMMENT 'In bytes (5MB default)',
  `allowed_extensions` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_upload_type` (`upload_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `upload_settings`
--

LOCK TABLES `upload_settings` WRITE;
/*!40000 ALTER TABLE `upload_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `upload_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `profile_picture` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `role` enum('admin','coordinator','supervisor','student') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `application_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `application_submitted_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `approval_notes` varchar(255) DEFAULT NULL,
  `disciplinary_remark` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'JuliusGomez','JuliusGomez','juluisgomez@gmail.com','',NULL,'$2y$10$O3c1pTPq4RNJgOdu22ltdO100ICm7slNH7ZsLmMHCY/ZdglUTu/ya',NULL,'supervisor',1,'2026-02-24 02:53:39','2026-02-24 02:53:39','approved',NULL,NULL,NULL,NULL,NULL,NULL),(2,'MarkVerzon','MarkVerzon','markverzon@gmail.com','',NULL,'$2y$10$TdhRjBcTSXzliYCnZyeKbeo35UWgShc3fX7d4Z7WhVl9coGXHxGJ2',NULL,'coordinator',1,'2026-02-24 02:56:30','2026-02-24 02:56:30','approved',NULL,NULL,NULL,NULL,NULL,NULL),(3,'Felix Luis Mateo','FelixLuisMateo','mateo.felixluis@gmail.com','',NULL,'$2y$10$PQtM4YQ0bmhzE8FFT.3CRu1Iuq.tDGQSlK6iQv.Hi/qX4hMhCXKu.',NULL,'student',1,'2026-02-24 02:57:53','2026-02-26 07:38:43','approved',NULL,NULL,NULL,NULL,NULL,NULL),(4,'Jomar','Jomar','jomarsangil@gmail.com','',NULL,'$2y$10$iE4WlTO6Ny3zNcC.v.1Tae04qQtUxcC2f.h9/v7VuKj7q3a4145HO',NULL,'admin',1,'2026-02-24 03:14:42','2026-03-11 05:12:39','approved',NULL,NULL,NULL,NULL,NULL,NULL),(5,'Ivan Sanchez','IvanSanchez','IvanSanchez@gmail.com','uploads/profile_pictures/student_2_1773217993.jpg',NULL,'$2y$10$G4AcrvLY6tYayygIQhJIfeQNievmy1uMNCLr7eTSi.f/9Ibibu1AW',NULL,'student',0,'2026-02-26 06:22:05','2026-03-11 08:36:01','approved',NULL,NULL,NULL,NULL,NULL,NULL),(6,'Tyron Jay Gonzales','TyronGonzales','Tyron@gmail.com','',NULL,'$2y$10$p6uQoFaN6mppBsGGH9iDJOUKVPChh9Oi2T93gUkRFCr/AhAlyLpm2',NULL,'student',1,'2026-02-26 06:44:27','2026-02-26 06:44:27','approved',NULL,NULL,NULL,NULL,NULL,NULL),(7,'Tester Admin','Testo','nozomigoodshot@gmail.com','assets/images/avatar/uploads/user_7_1773023141.jpg',NULL,'$2y$10$/E3oy5mfRyjc6eX1oCG1ROKxFAuyyR0QNVhOi6wYoWfBhOPmNOW3q',NULL,'admin',1,'2026-03-05 05:25:02','2026-03-09 02:25:41','approved',NULL,NULL,NULL,NULL,NULL,NULL),(13,'Heavn Sanchezss','GoldniWallys','GolsdniWally@gmail.com','',NULL,'$2y$10$YAf4XDHNxusAgGgXRdNHw.553D83okI3rDUkWrPvO9FwVJOSUoE6y',NULL,'student',0,'2026-03-11 05:25:58','2026-03-11 05:36:23','rejected','2026-03-11 13:25:58',7,NULL,'2026-03-11 13:36:23','',''),(14,'Heavns Sanchez','GoldniWallys','GoldniWally@gmail.coms','',NULL,'$2y$10$deVOQRpdfWMbVDm/Sh84q.QFrqb.Agg7jL6/O4fTmurjDXAD56XKa',NULL,'student',0,'2026-03-11 05:40:13','2026-03-11 07:55:21','rejected','2026-03-11 13:40:13',7,NULL,'2026-03-11 15:55:21','','');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'biotern_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-12  9:25:45
