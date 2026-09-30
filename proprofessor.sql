-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Sep 30, 2026 at 10:02 AM
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
-- Database: `proprofessor`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_events`
--

CREATE TABLE `academic_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `event_type` varchar(60) DEFAULT NULL,
  `event_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `institution_id`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `details`, `created_at`) VALUES
(1, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-19 05:16:32'),
(2, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-19 07:43:53'),
(3, 1, 1, 'user_create', 'user', 2, '::1', '{\"role\":\"hod\"}', '2026-09-19 07:48:59'),
(4, 1, 1, 'user_create', 'user', 3, '::1', '{\"role\":\"professor\"}', '2026-09-19 07:49:14'),
(5, 1, 1, 'user_create', 'user', 4, '::1', '{\"role\":\"student\"}', '2026-09-19 07:50:40'),
(6, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-19 07:50:45'),
(7, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-19 07:51:00'),
(8, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-19 07:51:34'),
(9, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-19 07:51:56'),
(10, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-19 08:05:21'),
(11, 1, 4, 'login', NULL, NULL, '::1', NULL, '2026-09-19 08:05:42'),
(12, 1, 4, 'logout', NULL, NULL, '::1', NULL, '2026-09-19 08:06:10'),
(13, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-19 08:06:46'),
(14, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-19 08:07:44'),
(15, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-19 08:07:57'),
(16, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-19 08:08:53'),
(17, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-19 08:09:05'),
(18, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-28 03:50:16'),
(19, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-28 04:01:31'),
(20, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-28 04:02:30'),
(21, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-28 04:12:19'),
(22, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-28 04:12:31'),
(23, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-28 04:45:30'),
(24, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-28 04:45:42'),
(25, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-28 05:28:34'),
(26, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-28 05:28:42'),
(27, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-28 05:37:47'),
(28, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-28 05:38:01'),
(29, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 03:23:46'),
(30, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 03:53:59'),
(31, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 03:56:35'),
(32, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-29 03:56:46'),
(33, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 03:56:57'),
(34, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 03:57:04'),
(35, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 03:58:41'),
(36, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-29 03:58:50'),
(37, 1, 1, 'user_create', 'user', 5, '::1', '{\"role\":\"professor\"}', '2026-09-29 03:59:15'),
(38, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 03:59:22'),
(39, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 03:59:30'),
(40, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 04:00:41'),
(41, 1, 5, 'login', NULL, NULL, '::1', NULL, '2026-09-29 04:00:51'),
(42, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 04:08:07'),
(43, 1, 5, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 04:18:50'),
(44, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 04:19:01'),
(45, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 05:03:47'),
(46, 1, 5, 'login', NULL, NULL, '::1', NULL, '2026-09-29 05:03:56'),
(47, 1, 5, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 05:04:53'),
(48, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 05:05:00'),
(49, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 05:06:21'),
(50, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-29 05:12:47'),
(51, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 05:12:54'),
(52, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 05:14:04'),
(53, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 05:15:24'),
(54, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 05:15:33'),
(55, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-29 05:16:01'),
(56, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 05:16:22'),
(57, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 07:10:45'),
(58, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-29 07:25:53'),
(59, 1, 5, 'login', NULL, NULL, '::1', NULL, '2026-09-29 07:33:27'),
(60, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:25:34'),
(61, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:26:20'),
(62, 1, 4, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:26:31'),
(63, 1, 4, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:30:30'),
(64, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:30:39'),
(65, 1, 1, 'user_create', 'user', 6, '::1', '{\"role\":\"hod\"}', '2026-09-30 03:31:22'),
(66, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:31:27'),
(67, 1, 6, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:31:36'),
(68, 1, 6, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:31:49'),
(69, 1, 4, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:32:03'),
(70, 1, 4, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:35:37'),
(71, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:35:46'),
(72, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:45:45'),
(73, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:50:22'),
(74, 1, 4, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:50:31'),
(75, 1, 4, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:50:55'),
(76, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:51:04'),
(77, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:51:09'),
(78, 1, 4, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:51:19'),
(79, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 03:56:43'),
(80, 1, 4, 'login', NULL, NULL, '::1', NULL, '2026-09-30 03:56:58'),
(81, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:04:08'),
(82, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 04:18:05'),
(83, 1, 5, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:18:18'),
(84, 1, 5, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 04:23:59'),
(85, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:24:13'),
(86, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:41:04'),
(87, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:41:21'),
(88, 1, 4, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 04:42:52'),
(89, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:43:11'),
(90, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:48:03'),
(91, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 04:50:39'),
(92, 1, 4, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:50:50'),
(93, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 04:55:09'),
(94, 1, 5, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:55:19'),
(95, 1, 5, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 04:59:43'),
(96, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-09-30 04:59:51'),
(97, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 05:00:28'),
(98, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 05:01:00'),
(99, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 05:17:36'),
(100, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 05:17:47'),
(101, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 05:17:50'),
(102, 1, 5, 'login', NULL, NULL, '::1', NULL, '2026-09-30 05:18:02'),
(103, 1, 5, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 05:34:25'),
(104, 1, 6, 'login', NULL, NULL, '::1', NULL, '2026-09-30 05:34:36'),
(105, 1, 6, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 05:34:53'),
(106, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 05:35:53'),
(107, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 05:39:48'),
(108, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-30 05:39:56'),
(109, 1, 2, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 05:56:00'),
(110, 1, 3, 'login', NULL, NULL, '::1', NULL, '2026-09-30 05:56:17'),
(111, 1, 3, 'logout', NULL, NULL, '::1', NULL, '2026-09-30 06:04:52'),
(112, 1, 2, 'login', NULL, NULL, '::1', NULL, '2026-09-30 06:05:03');

-- --------------------------------------------------------

--
-- Table structure for table `admin_hod_announcements`
--

CREATE TABLE `admin_hod_announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `recipient_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_mime_type` varchar(100) DEFAULT NULL,
  `attachment_size` int(10) UNSIGNED DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_hod_announcements`
--

INSERT INTO `admin_hod_announcements` (`id`, `institution_id`, `admin_id`, `title`, `body`, `recipient_count`, `attachment_path`, `attachment_original_name`, `attachment_mime_type`, `attachment_size`, `meta`, `created_at`) VALUES
(8, 1, 1, 'Holiday Notice', 'This Week Friday Going to be Holiday Because of Gandhi Jayanthi', 1, NULL, NULL, NULL, NULL, '{\"sender_name\":\"College Admin\",\"audience\":\"ALL_STUDENTS\",\"notice_type\":\"EVENT\",\"has_attachment\":false,\"attachment_original_name\":null}', '2026-09-30 03:50:19');

-- --------------------------------------------------------

--
-- Table structure for table `ai_chats`
--

CREATE TABLE `ai_chats` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_chat_messages`
--

CREATE TABLE `ai_chat_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `chat_id` int(10) UNSIGNED NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` longtext NOT NULL,
  `citations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`citations`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_generations`
--

CREATE TABLE `ai_generations` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `module` varchar(64) NOT NULL,
  `prompt_code` varchar(64) DEFAULT NULL,
  `input_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_payload`)),
  `output_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_payload`)),
  `model` varchar(80) DEFAULT NULL,
  `tokens_in` int(10) UNSIGNED DEFAULT NULL,
  `tokens_out` int(10) UNSIGNED DEFAULT NULL,
  `latency_ms` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('success','error','partial') DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  `ref_type` varchar(40) DEFAULT NULL,
  `ref_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_generations`
--

INSERT INTO `ai_generations` (`id`, `institution_id`, `user_id`, `module`, `prompt_code`, `input_payload`, `output_payload`, `model`, `tokens_in`, `tokens_out`, `latency_ms`, `status`, `error_message`, `ref_type`, `ref_id`, `created_at`) VALUES
(1, 1, 1, 'formula', 'formula', '{\"text\":\"Average of C1A and C1B\"}', '{\"name\":\"Parsed Formula\",\"components\":[{\"code\":\"cia1\",\"label\":\"CIA 1\",\"max\":50,\"weight\":0.3},{\"code\":\"cia2\",\"label\":\"CIA 2\",\"max\":50,\"weight\":0.3},{\"code\":\"assignment\",\"label\":\"Assignment\",\"max\":5,\"weight\":0.2},{\"code\":\"attendance\",\"label\":\"Attendance\",\"max\":5,\"weight\":0.2}],\"expression\":\"((cia1+cia2)\\/2)*(15\\/50)+assignment+attendance\",\"total_max\":25,\"demo\":true}', 'gemini-2.5-flash', NULL, NULL, 0, 'success', NULL, NULL, NULL, '2026-09-19 05:17:26'),
(2, 1, 3, 'course_plan', 'course_plan', '{\"subject\":\"Database Management Systems\",\"credits\":\"4\",\"university\":\"Autonomous\",\"template\":\"standard\",\"provider\":\"gemini\"}', '{\"title\":\"Database Management Systems\",\"learning_outcomes\":[\"CO1: Analyze database requirements and design conceptual schemas using Entity-Relationship (ER) and Extended ER (EER) modeling techniques.\",\"CO2: Formulate relational algebra expressions and write optimized Structured Query Language (SQL) queries to perform complex data manipulation and retrieval.\",\"CO3: Apply normalization theory to design logical relational database schemas, eliminating redundancies and anomalies up to Fifth Normal Form (5NF).\",\"CO4: Evaluate transaction schedules for serializability and design concurrency control mechanisms using lock-based and timestamp-based protocols.\",\"CO5: Analyze physical storage structures, implement efficient indexing mechanisms using B\\/B+ Trees, and design database recovery strategies.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to DBMS & Database Architecture\",\"hours\":12,\"topics\":[\"Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS\",\"Data Abstraction, Data Independence (Physical and Logical)\",\"Three-Schema Architecture (Internal, Conceptual, External)\",\"Database Languages (DDL, DML, DCL, TCL), Database Interfaces\",\"Roles of Database Users and DBAs\",\"ER Model: Entities, Attributes, Entity Sets, Relationships, Mapping Cardinalities, Keys (Super, Candidate, Primary, Foreign)\",\"Extended ER (EER) Modeling: Specialization, Generalization, Aggregation\",\"Reduction of ER\\/EER Diagrams to Relational Tables\"],\"outcomes\":[\"Differentiate between traditional file systems and modern DBMS architectures.\",\"Design conceptual schemas using ER and EER diagrams for real-world enterprise scenarios.\",\"Map complex ER\\/EER diagrams into structurally sound relational database tables.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Chalk and Board for architectural diagrams\",\"Collaborative group activity for ER modeling of real-world case studies\",\"Flipped classroom on DBMS vs File Systems\"],\"assessment\":[\"Class Test on ER-to-Relational mapping rules\",\"Evaluation of ER diagram design assignment for a given case study\"]},{\"unit_number\":2,\"title\":\"Relational Model & Query Languages\",\"hours\":12,\"topics\":[\"Structure of Relational Databases, Relational Model Constraints (Domain, Entity Integrity, Referential Integrity)\",\"Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference, Cartesian Product, Rename)\",\"Relational Algebra: Additional Operations (Join, Division, Intersection)\",\"SQL DDL Commands (CREATE, ALTER, DROP, TRUNCATE) and DML Commands (INSERT, UPDATE, DELETE, SELECT)\",\"Integrity Constraints: NOT NULL, UNIQUE, PRIMARY KEY, FOREIGN KEY, CHECK, DEFAULT\",\"Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries, Set Operations, Inner\\/Outer Joins\",\"Views & Indexes: Creating Views, Updatable Views, Index Structures\"],\"outcomes\":[\"Formulate formal relational algebra expressions for complex data retrieval queries.\",\"Construct robust SQL queries utilizing joins, nested subqueries, and aggregation to solve business logic requirements.\",\"Implement and enforce domain, entity, and referential integrity constraints in SQL.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Hands-on laboratory sessions using PostgreSQL\\/MySQL\",\"Problem-solving sessions for Relational Algebra\",\"Interactive live-coding demonstrations\"],\"assessment\":[\"Laboratory practical exam on SQL queries and Joins\",\"Online coding challenge on nested subqueries and constraints\"]},{\"unit_number\":3,\"title\":\"Relational Database Design & Normalization\",\"hours\":12,\"topics\":[\"Pitfalls in Relational Design: Data Redundancy, Insertion, Deletion, and Update Anomalies\",\"Functional Dependencies: Definition, Trivial and Non-trivial Dependencies, Closure of Functional Dependencies, Armstrong\\u2019s Axioms, Canonical Cover\",\"First Normal Form (1NF), Second Normal Form (2NF), Third Normal Form (3NF)\",\"Boyce-Codd Normal Form (BCNF)\",\"Higher Normal Forms: Fourth Normal Form (4NF - Multivalued Dependencies), Fifth Normal Form (5NF - Join Dependencies)\",\"Decomposition Properties: Lossless-Join Decomposition, Dependency-Preserving Decomposition\"],\"outcomes\":[\"Identify and analyze design anomalies in poorly structured relational schemas.\",\"Compute functional dependency closures, canonical covers, and candidate keys for relational schemas.\",\"Decompose relational schemas up to BCNF\\/5NF ensuring lossless-join and dependency-preservation properties.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Problem-driven learning using step-by-step normalization exercises\",\"Peer instruction on decomposition properties\",\"Guided design reviews of student-created schemas\"],\"assessment\":[\"Mid-Semester Examination containing analytical normalization problems\",\"Home assignment on finding Canonical Cover and testing Lossless-Join property\"]},{\"unit_number\":4,\"title\":\"Transaction Management & Concurrency Control\",\"hours\":12,\"topics\":[\"Transaction Concepts: Definition, Transaction States, ACID Properties\",\"Schedules: Concurrent Executions, Serial Schedules, Serializability (Conflict and View Serializability)\",\"Recoverability: Cascadeless and Recoverable Schedules\",\"Lock-Based Protocols: Shared\\/Exclusive Locks, Two-Phase Locking (2PL - Strict 2PL, Rigorous 2PL)\",\"Deadlocks: Deadlock Prevention, Detection, and Recovery\",\"Timestamp-Based & Validation-Based Protocols, Thomas\' Write Rule\"],\"outcomes\":[\"Analyze concurrent transaction schedules for conflict and view serializability.\",\"Apply lock-based and timestamp-based protocols to guarantee database consistency and isolation.\",\"Formulate strategies to prevent, detect, and resolve deadlocks in concurrent transaction environments.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Visual tracing of concurrent execution timelines\",\"Case-study analysis of financial transaction failures\",\"Interactive simulation tools for Lock-based protocols\"],\"assessment\":[\"Quizzes on Serializability and 2PL execution paths\",\"Analytical problem-solving test on Deadlock detection algorithms\"]},{\"unit_number\":5,\"title\":\"Storage Structures, Indexing & Recovery\",\"hours\":12,\"topics\":[\"Storage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels\",\"Indexing & Hashing: Primary, Secondary, Clustered Index, Sparse vs Dense Index\",\"Dynamic Hashing, Tree-Structured Indexing (B-Trees and B+-Trees)\",\"Database Recovery Techniques: Failure Classification (System Crash, Transaction Failure, Disk Failure)\",\"Log-Based Recovery (Deferred Database Modification, Immediate Database Modification)\",\"Checkpoints and Shadow Paging\"],\"outcomes\":[\"Compare different physical file organizations and RAID levels for optimal database performance.\",\"Construct and manipulate B-Trees and B+-Trees for indexing database attributes.\",\"Evaluate recovery algorithms (Deferred vs Immediate modifications) to restore database consistency after failures.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Animation-based demonstration of B+ Tree insertions and deletions\",\"Comparative analysis of RAID levels using real-world performance metrics\",\"Step-by-step walkthrough of log-based recovery scenarios\"],\"assessment\":[\"Assignment on B+ Tree dry-runs (insertion\\/deletion steps)\",\"End-Semester Examination questions on Log-based recovery and Checkpointing\"]}],\"weekly_plan\":[{\"week\":1,\"topics\":[\"Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS\",\"Data Abstraction, Data Independence (Physical and Logical)\"],\"pedagogy\":\"Interactive Lecture & Comparative Discussion\"},{\"week\":2,\"topics\":[\"Three-Schema Architecture, Database Languages (DDL, DML, DCL, TCL), Database Interfaces\",\"Roles of Database Users and DBAs\"],\"pedagogy\":\"Chalk and Board, Role-play activity for DBA vs User roles\"},{\"week\":3,\"topics\":[\"ER Model: Entities, Attributes, Relationships, Keys\",\"EER Modeling: Generalization, Specialization, Aggregation\",\"Reduction of ER\\/EER Diagrams to Relational Tables\"],\"pedagogy\":\"Collaborative Group Design Session, Case Study Analysis\"},{\"week\":4,\"topics\":[\"Structure of Relational Databases, Relational Model Constraints\",\"Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference)\"],\"pedagogy\":\"Problem-Solving Session, Interactive Quizzes\"},{\"week\":5,\"topics\":[\"Relational Algebra: Cartesian Product, Rename, Join, Division, Intersection\",\"SQL DDL & DML Commands, Basic Queries\"],\"pedagogy\":\"Hands-on Lab Session, Live Coding Demonstrations\"},{\"week\":6,\"topics\":[\"Integrity Constraints, Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries\",\"Views & Indexes: Creation and Updatability\"],\"pedagogy\":\"Lab-based Query Optimization Exercises, Peer Code Reviews\"},{\"week\":7,\"topics\":[\"Pitfalls in Relational Design, Data Redundancy, Anomalies\",\"Functional Dependencies, Closure of FDs, Armstrong\'s Axioms\"],\"pedagogy\":\"Case-driven Lecture, Mathematical Proof Walkthroughs\"},{\"week\":8,\"topics\":[\"Canonical Cover, First Normal Form (1NF), Second Normal Form (2NF), Third Normal Form (3NF)\"],\"pedagogy\":\"Step-by-step Problem Solving, Flipped Classroom on Anomalies\"},{\"week\":9,\"topics\":[\"Boyce-Codd Normal Form (BCNF), Higher Normal Forms (4NF, 5NF)\",\"Decomposition Properties: Lossless-Join and Dependency Preservation\"],\"pedagogy\":\"Analytical Problem Solving, Peer Instruction\"},{\"week\":10,\"topics\":[\"Transaction Concepts, ACID Properties, Transaction States\",\"Schedules, Serializability (Conflict and View Serializability)\"],\"pedagogy\":\"Visual Timeline Tracing, Real-world Transaction Failure Case Studies\"},{\"week\":11,\"topics\":[\"Recoverability (Cascadeless and Recoverable Schedules)\",\"Lock-Based Protocols: Shared\\/Exclusive Locks, Two-Phase Locking (2PL, Strict 2PL, Rigorous 2PL)\"],\"pedagogy\":\"Interactive Simulation, Scenario-based Analysis\"},{\"week\":12,\"topics\":[\"Deadlocks: Prevention, Detection, and Recovery\",\"Timestamp-Based & Validation-Based Protocols, Thomas\' Write Rule\"],\"pedagogy\":\"Algorithmic Dry-runs, Group Discussion on Deadlock Trade-offs\"},{\"week\":13,\"topics\":[\"Storage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels\",\"Indexing: Primary, Secondary, Clustered, Sparse vs Dense Index\"],\"pedagogy\":\"Comparative Analysis, Architectural Diagrams\"},{\"week\":14,\"topics\":[\"Dynamic Hashing, Tree-Structured Indexing (B-Trees and B+-Trees)\"],\"pedagogy\":\"Animation-based Tutorials, Step-by-step Tree Modification Exercises\"},{\"week\":15,\"topics\":[\"Database Recovery Techniques: Failure Classification, Log-Based Recovery (Deferred & Immediate Modification)\",\"Checkpoints and Shadow Paging\"],\"pedagogy\":\"Trace-based Problem Solving, Course Review, and Mock Exam\"}],\"resources\":[{\"type\":\"Textbook\",\"details\":\"Silberschatz, A., Korth, H. F., & Sudarshan, S. (2020). Database System Concepts (7th ed.). McGraw-Hill.\"},{\"type\":\"Reference Book\",\"details\":\"Elmasri, R., & Navathe, S. B. (2017). Fundamentals of Database Systems (7th ed.). Pearson.\"},{\"type\":\"Online Course\",\"details\":\"NPTEL: \'Database Management Systems\' by Prof. Partha Pratim Das, IIT Kharagpur.\"},{\"type\":\"Tool\",\"details\":\"PostgreSQL Open-Source Relational Database Management System.\"}],\"expert_advice\":[\"Ensure students write SQL queries by hand before executing them in the lab. This builds strong syntax retention and logical query formulation.\",\"Emphasize the mathematical foundations of relational algebra as it directly translates to query optimization in real-world database engines.\",\"When teaching normalization, use realistic, messy business spreadsheets as starting points rather than clean, pre-simplified textbook relations.\",\"Integrate a continuous mini-project where students design, normalize, implement, and query a database for a domain of their choice throughout the semester.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":35,\"K4\":25,\"K5\":10,\"K6\":0},\"ai_score\":96}', 'gemini-3.5-flash', NULL, NULL, 24815, 'success', NULL, NULL, NULL, '2026-09-19 07:55:55');
INSERT INTO `ai_generations` (`id`, `institution_id`, `user_id`, `module`, `prompt_code`, `input_payload`, `output_payload`, `model`, `tokens_in`, `tokens_out`, `latency_ms`, `status`, `error_message`, `ref_type`, `ref_id`, `created_at`) VALUES
(3, 1, 3, 'lesson', 'lesson', '{\"plan_id\":1,\"provider\":\"gemini\"}', '{\"sessions\":[{\"session_number\":1,\"title\":\"Data vs Information & Evolution of DBMS\",\"duration_mins\":60,\"objectives\":[\"Distinguish between raw data and structured information.\",\"Explain the historical evolution from manual record-keeping to computerized file systems.\"],\"teaching_method\":\"Flipped classroom and interactive lecture\",\"activities\":[\"Group discussion comparing a physical ledger with a digital spreadsheet.\"],\"formative_assessment\":[\"One-minute paper summarizing why data requires context to become information.\"],\"engagement\":[\"Think-Pair-Share: Ask students to identify three daily activities that generate data.\"]},{\"session_number\":2,\"title\":\"File-Processing Systems vs DBMS & Advantages of DBMS\",\"duration_mins\":60,\"objectives\":[\"Identify the limitations of traditional file-processing systems.\",\"Explain how DBMS solves data redundancy, inconsistency, and security issues.\"],\"teaching_method\":\"Comparative analysis and case-driven lecture\",\"activities\":[\"Analyze a scenario of a university using separate text files for student records and tuition payments.\"],\"formative_assessment\":[\"A quick 3-question poll on file system anomalies.\"],\"engagement\":[\"Role-play: Students act as different departments trying to update a shared student address in isolated files.\"]},{\"session_number\":3,\"title\":\"Data Abstraction and Data Independence\",\"duration_mins\":60,\"objectives\":[\"Explain the three levels of data abstraction.\",\"Differentiate between physical and logical data independence.\"],\"teaching_method\":\"Chalk and Board with visual diagrams\",\"activities\":[\"Draw and label the abstraction layers for a sample e-commerce application.\"],\"formative_assessment\":[\"Spot-check questions asking students to categorize changes as physical or logical.\"],\"engagement\":[\"Analogy mapping: Comparing database abstraction to driving a car without knowing engine mechanics.\"]},{\"session_number\":4,\"title\":\"Three-Schema Architecture\",\"duration_mins\":60,\"objectives\":[\"Describe the purpose of the ANSI-SPARC Three-Schema Architecture.\",\"Explain how schema mapping supports data independence.\"],\"teaching_method\":\"Interactive lecture with architectural walkthroughs\",\"activities\":[\"Trace a query from the external view down to physical storage blocks.\"],\"formative_assessment\":[\"Sketching the three-schema architecture from memory with correct labels.\"],\"engagement\":[\"Peer instruction: Explain to your neighbor why the conceptual schema is the heart of the architecture.\"]},{\"session_number\":5,\"title\":\"Database Languages (DDL, DML, DCL, TCL) & Interfaces\",\"duration_mins\":60,\"objectives\":[\"Categorize SQL statements into DDL, DML, DCL, and TCL.\",\"Identify different types of database interfaces.\"],\"teaching_method\":\"Interactive categorization and live syntax demonstration\",\"activities\":[\"Sort a mixed list of SQL commands into their respective sub-language categories.\"],\"formative_assessment\":[\"Clicker quiz on classifying commands like \'GRANT\', \'COMMIT\', and \'ALTER\'.\"],\"engagement\":[\"Speed-sorting game: Teams compete to classify commands on a shared virtual board.\"]},{\"session_number\":6,\"title\":\"Roles of Database Users and DBAs\",\"duration_mins\":60,\"objectives\":[\"Identify the responsibilities of Database Administrators (DBAs).\",\"Differentiate between naive users, application programmers, and sophisticated users.\"],\"teaching_method\":\"Role-play and group discussion\",\"activities\":[\"Simulate a database crash scenario and assign recovery tasks to appropriate roles.\"],\"formative_assessment\":[\"Match-the-following worksheet pairing user types with their typical database interactions.\"],\"engagement\":[\"Mock job interview: Students interview each other for a DBA role based on a list of responsibilities.\"]},{\"session_number\":7,\"title\":\"ER Model: Entities, Attributes, and Keys\",\"duration_mins\":60,\"objectives\":[\"Define entities, attributes, and entity sets.\",\"Distinguish between primary, candidate, super, and foreign keys.\"],\"teaching_method\":\"Chalk and Board with step-by-step definition building\",\"activities\":[\"Analyze a simple scenario to identify candidate keys and select the best primary key.\"],\"formative_assessment\":[\"Short quiz on identifying candidate keys from a given set of functional properties.\"],\"engagement\":[\"Brainstorming: List all possible attributes for a \'Car\' entity and classify them (simple vs. composite).\"]},{\"session_number\":8,\"title\":\"ER Model: Relationships and Mapping Cardinalities\",\"duration_mins\":60,\"objectives\":[\"Model relationships between entities with appropriate structural constraints.\",\"Determine mapping cardinalities (1:1, 1:N, N:M) for real-world scenarios.\"],\"teaching_method\":\"Collaborative group activity and case study analysis\",\"activities\":[\"Draw ER fragments for a hospital management system (Doctors, Patients, Appointments).\"],\"formative_assessment\":[\"Peer evaluation of ER relationship diagrams drawn by other groups.\"],\"engagement\":[\"Debate: Discuss whether a \'Student-Course\' relationship should be 1:N or N:M under different school policies.\"]},{\"session_number\":9,\"title\":\"Extended ER (EER) Modeling: Specialization and Generalization\",\"duration_mins\":60,\"objectives\":[\"Apply specialization and generalization to represent inheritance hierarchies.\",\"Enforce completeness and disjointness constraints on specialization.\"],\"teaching_method\":\"Problem-driven learning with hierarchical diagrams\",\"activities\":[\"Design an EER diagram for a bank account system with Savings and Checking accounts.\"],\"formative_assessment\":[\"Classifying a given hierarchy as total\\/partial and disjoint\\/overlapping.\"],\"engagement\":[\"Object-oriented analogy: Comparing EER specialization to class inheritance in Java\\/C++.\"]},{\"session_number\":10,\"title\":\"Extended ER (EER) Modeling: Aggregation\",\"duration_mins\":60,\"objectives\":[\"Explain the concept of aggregation in EER modeling.\",\"Identify scenarios where aggregation is required over ternary relationships.\"],\"teaching_method\":\"Interactive lecture and comparative modeling\",\"activities\":[\"Model a scenario where a manager supervises a specific project-employee assignment.\"],\"formative_assessment\":[\"Draw-along exercise: Correcting a flawed ternary relationship using aggregation.\"],\"engagement\":[\"Problem-solving challenge: \'Why can\'t we just use a regular relationship here?\' group discussion.\"]},{\"session_number\":11,\"title\":\"Reduction of ER Diagrams to Relational Tables - Part 1\",\"duration_mins\":60,\"objectives\":[\"Apply mapping rules to convert strong and weak entity sets into relational tables.\",\"Map 1:1 and 1:N relationships to tables using foreign keys.\"],\"teaching_method\":\"Step-by-step algorithmic mapping walkthrough\",\"activities\":[\"Convert a basic Company ER diagram into a set of SQL-ready table schemas.\"],\"formative_assessment\":[\"Individual mapping exercise evaluated via real-time board work.\"],\"engagement\":[\"Pairs match: Match ER components to their corresponding relational schema representation.\"]},{\"session_number\":12,\"title\":\"Reduction of EER Diagrams to Relational Tables - Part 2\",\"duration_mins\":60,\"objectives\":[\"Map N:M relationships and multi-valued attributes to relational tables.\",\"Convert EER specialization hierarchies (disjoint\\/overlapping) into tables.\"],\"teaching_method\":\"Problem-solving workshop\",\"activities\":[\"Transform a complex EER diagram with a specialization hierarchy into a relational schema.\"],\"formative_assessment\":[\"Class test on ER-to-Relational mapping rules.\"],\"engagement\":[\"Design review: Critique a poorly mapped schema and point out redundant tables.\"]},{\"session_number\":13,\"title\":\"Structure of Relational Databases & Domain Constraints\",\"duration_mins\":60,\"objectives\":[\"Define the mathematical concepts of relations, tuples, attributes, and domains.\",\"Explain domain constraints and their role in maintaining data integrity.\"],\"teaching_method\":\"Mathematical lecture and interactive definitions\",\"activities\":[\"Define domains for attributes of an online store (e.g., email, price, quantity).\"],\"formative_assessment\":[\"Short quiz on identifying valid and invalid tuple values based on domain constraints.\"],\"engagement\":[\"Think-Pair-Share: How do domain constraints prevent bad data entry in web forms?\"]},{\"session_number\":14,\"title\":\"Entity Integrity and Referential Integrity Constraints\",\"duration_mins\":60,\"objectives\":[\"Explain the Entity Integrity constraint regarding primary keys.\",\"Formulate and enforce Referential Integrity constraints using foreign keys.\"],\"teaching_method\":\"Visual tracing of constraint violations\",\"activities\":[\"Analyze a set of tables to spot violations of entity and referential integrity.\"],\"formative_assessment\":[\"Solve a worksheet on ON DELETE CASCADE and ON DELETE SET NULL behaviors.\"],\"engagement\":[\"Interactive simulation: Students act as database engines blocking invalid insert\\/delete operations.\"]},{\"session_number\":15,\"title\":\"Relational Algebra: Select, Project, and Rename Operations\",\"duration_mins\":60,\"objectives\":[\"Write formal relational algebra expressions using Select (\\u03c3) and Project (\\u03c0).\",\"Apply Rename (\\u03c1) operations to resolve relation name conflicts.\"],\"teaching_method\":\"Problem-solving session with step-by-step syntax building\",\"activities\":[\"Write expressions to retrieve specific rows and columns from a \'Library\' database.\"],\"formative_assessment\":[\"Board-work challenge: Write the relational algebra expression for a given natural language query.\"],\"engagement\":[\"Speed challenge: Who can write the shortest correct expression for a query?\"]},{\"session_number\":16,\"title\":\"Relational Algebra: Set Operations\",\"duration_mins\":60,\"objectives\":[\"Apply Union (\\u222a), Set Difference (\\u2212), and Cartesian Product (\\u00d7) in relational algebra.\",\"Explain the prerequisite of union compatibility.\"],\"teaching_method\":\"Mathematical proof and set-theory visualization\",\"activities\":[\"Perform set operations on small sample tables of \'Online Customers\' and \'In-Store Customers\'.\"],\"formative_assessment\":[\"Identify why two given schemas cannot be combined using the Union operator.\"],\"engagement\":[\"Venn diagram visualization: Draw set operations before writing the relational algebra.\"]},{\"session_number\":17,\"title\":\"Relational Algebra: Join Operations\",\"duration_mins\":60,\"objectives\":[\"Differentiate between Theta Join, Equijoin, and Natural Join.\",\"Formulate complex queries using join operations.\"],\"teaching_method\":\"Visual tracing of join execution paths\",\"activities\":[\"Manually compute the natural join of two relations with shared attributes.\"],\"formative_assessment\":[\"Solve a set of 3 join-computation problems.\"],\"engagement\":[\"Interactive puzzle: Match join conditions to their resulting output tables.\"]},{\"session_number\":18,\"title\":\"Relational Algebra: Division and Intersection\",\"duration_mins\":60,\"objectives\":[\"Explain the semantics of the Division (\\u00f7) operator.\",\"Formulate queries for \'all\' or \'every\' requirements using division.\"],\"teaching_method\":\"Problem-driven learning with step-by-step breakdown\",\"activities\":[\"Solve the classic \'find students who have taken all courses in CS\' query using division.\"],\"formative_assessment\":[\"Decompose a division operation into fundamental operators (Select, Project, Cartesian Product).\"],\"engagement\":[\"Group challenge: Translate a complex natural language query into a division expression.\"]},{\"session_number\":19,\"title\":\"SQL DDL and Basic DML Commands\",\"duration_mins\":60,\"objectives\":[\"Write SQL DDL commands to create, alter, and drop tables.\",\"Write basic DML commands (INSERT, UPDATE, DELETE) to manipulate data.\"],\"teaching_method\":\"Hands-on laboratory session and live-coding\",\"activities\":[\"Write and execute SQL scripts to build a schema for a student enrollment system.\"],\"formative_assessment\":[\"Live execution check of DDL scripts in PostgreSQL.\"],\"engagement\":[\"Code-along: Build a database schema step-by-step with the instructor.\"]},{\"session_number\":20,\"title\":\"Enforcing Integrity Constraints in SQL\",\"duration_mins\":60,\"objectives\":[\"Implement NOT NULL, UNIQUE, PRIMARY KEY, and FOREIGN KEY constraints in SQL.\",\"Apply CHECK and DEFAULT constraints to enforce business rules.\"],\"teaching_method\":\"Hands-on laboratory session\",\"activities\":[\"Modify existing tables to add constraints and test them by attempting to insert invalid data.\"],\"formative_assessment\":[\"Verify constraint enforcement by writing queries that trigger constraint violations.\"],\"engagement\":[\"Break-the-database: Students try to insert \'illegal\' records into a classmate\'s database.\"]},{\"session_number\":21,\"title\":\"SQL Aggregation and Grouping\",\"duration_mins\":60,\"objectives\":[\"Apply aggregate functions (SUM, AVG, COUNT, MIN, MAX) in SQL.\",\"Formulate queries using GROUP BY and HAVING clauses.\"],\"teaching_method\":\"Interactive live-coding and query optimization\",\"activities\":[\"Write queries to find the average salary of employees in each department with more than 5 employees.\"],\"formative_assessment\":[\"Differentiate between WHERE and HAVING clauses in a quick quiz.\"],\"engagement\":[\"Query debugging: Find the syntax error in a given GROUP BY query.\"]},{\"session_number\":22,\"title\":\"Nested Subqueries and Set Operations in SQL\",\"duration_mins\":60,\"objectives\":[\"Write nested subqueries using IN, EXISTS, UNIQUE, and ALL\\/ANY comparison operators.\",\"Combine query results using UNION, INTERSECT, and EXCEPT.\"],\"teaching_method\":\"Hands-on laboratory session\",\"activities\":[\"Implement complex nested queries to solve multi-level data retrieval problems.\"],\"formative_assessment\":[\"Online coding challenge on nested subqueries.\"],\"engagement\":[\"Peer code review: Compare nested subquery solutions with equivalent JOIN solutions.\"]},{\"session_number\":23,\"title\":\"Creating and Managing Views in SQL\",\"duration_mins\":60,\"objectives\":[\"Create, update, and drop views in SQL.\",\"Explain the security and abstraction benefits of views.\"],\"teaching_method\":\"Interactive live-coding demonstration\",\"activities\":[\"Create a restricted view of employee data for HR assistants and test access permissions.\"],\"formative_assessment\":[\"Identify whether a given view is updatable based on SQL standards.\"],\"engagement\":[\"Discussion: How do views act as a security layer in enterprise databases?\"]},{\"session_number\":24,\"title\":\"Introduction to SQL Index Structures\",\"duration_mins\":60,\"objectives\":[\"Create and drop indexes in SQL.\",\"Explain how indexes speed up query retrieval at the cost of update performance.\"],\"teaching_method\":\"Hands-on laboratory session and performance analysis\",\"activities\":[\"Measure query execution time before and after creating an index on a large dataset.\"],\"formative_assessment\":[\"Laboratory practical exam on SQL queries, Joins, and Index creation.\"],\"engagement\":[\"Performance race: Compare query execution plans using EXPLAIN in PostgreSQL.\"]},{\"session_number\":25,\"title\":\"Pitfalls in Relational Design & Data Redundancy\",\"duration_mins\":60,\"objectives\":[\"Identify data redundancy in poorly designed relational schemas.\",\"Explain the consequences of uncontrolled redundancy on storage and performance.\"],\"teaching_method\":\"Problem-driven learning using messy spreadsheets\",\"activities\":[\"Analyze a single-table university spreadsheet containing student, course, and instructor details.\"],\"formative_assessment\":[\"List three distinct redundant data points in the provided spreadsheet.\"],\"engagement\":[\"Think-Pair-Share: How does redundancy lead to inconsistent data over time?\"]},{\"session_number\":26,\"title\":\"Database Anomalies: Insertion, Deletion, and Update\",\"duration_mins\":60,\"objectives\":[\"Define and demonstrate insertion anomalies.\",\"Define and demonstrate deletion and update anomalies.\"],\"teaching_method\":\"Interactive scenario-based analysis\",\"activities\":[\"Perform mock database updates on a flat table to trigger anomalies.\"],\"formative_assessment\":[\"Identify the type of anomaly occurring in a set of database operation scenarios.\"],\"engagement\":[\"Role-play: Students act as data entry operators dealing with the frustration of anomalies.\"]},{\"session_number\":27,\"title\":\"Functional Dependencies: Definition and Types\",\"duration_mins\":60,\"objectives\":[\"Define functional dependencies (FDs) mathematically.\",\"Distinguish between trivial and non-trivial functional dependencies.\"],\"teaching_method\":\"Mathematical lecture and proof walkthroughs\",\"activities\":[\"Identify valid functional dependencies from a given instance of a relation.\"],\"formative_assessment\":[\"Short quiz on verifying FDs on a sample relation instance.\"],\"engagement\":[\"Concept mapping: Relate functional dependencies to mathematical functions (y = f(x)).\"]},{\"session_number\":28,\"title\":\"Armstrong\'s Axioms & Closure of Functional Dependencies\",\"duration_mins\":60,\"objectives\":[\"Apply Armstrong\'s Axioms (reflexivity, augmentation, transitivity) to derive FDs.\",\"Compute the attribute closure (X+) for a set of attributes.\"],\"teaching_method\":\"Step-by-step problem-solving session\",\"activities\":[\"Compute candidate keys of a relation using attribute closure algorithms.\"],\"formative_assessment\":[\"Solve a set of 3 attribute closure problems on the board.\"],\"engagement\":[\"Speed-run: Find all candidate keys for a given schema and FD set in under 3 minutes.\"]},{\"session_number\":29,\"title\":\"Canonical Cover of Functional Dependencies\",\"duration_mins\":60,\"objectives\":[\"Define extraneous attributes in a set of functional dependencies.\",\"Compute the canonical cover (Fc) for a given set of FDs.\"],\"teaching_method\":\"Algorithmic dry-runs and peer instruction\",\"activities\":[\"Apply the canonical cover algorithm step-by-step to simplify a complex set of FDs.\"],\"formative_assessment\":[\"Home assignment on finding Canonical Cover and testing Lossless-Join property.\"],\"engagement\":[\"Peer review: Swap simplified FD sets and check for missing or extra dependencies.\"]},{\"session_number\":30,\"title\":\"First Normal Form (1NF) and Second Normal Form (2NF)\",\"duration_mins\":60,\"objectives\":[\"Define atomic domains and normalize a relation to 1NF.\",\"Identify partial functional dependencies and normalize a relation to 2NF.\"],\"teaching_method\":\"Problem-driven learning with step-by-step normalization\",\"activities\":[\"Decompose a non-1NF table containing multi-valued attributes into 2NF tables.\"],\"formative_assessment\":[\"Identify partial dependencies in a given schema and propose a 2NF decomposition.\"],\"engagement\":[\"Interactive poll: Is a composite attribute a violation of 1NF?\"]},{\"session_number\":31,\"title\":\"Third Normal Form (3NF)\",\"duration_mins\":60,\"objectives\":[\"Define transitive functional dependencies.\",\"Normalize a relation to 3NF using the formal definition.\"],\"teaching_method\":\"Step-by-step problem solving\",\"activities\":[\"Decompose a 2NF schema with transitive dependencies into 3NF.\"],\"formative_assessment\":[\"Check if a given schema is in 3NF by analyzing its FDs.\"],\"engagement\":[\"Critique session: Analyze a real-world schema and identify transitive dependencies.\"]},{\"session_number\":32,\"title\":\"Boyce-Codd Normal Form (BCNF)\",\"duration_mins\":60,\"objectives\":[\"Compare 3NF and BCNF definitions.\",\"Decompose a relation into BCNF when non-trivial FDs violate the superkey condition.\"],\"teaching_method\":\"Comparative analysis and problem-solving\",\"activities\":[\"Analyze a classic scenario (Student, Advisor, Subject) that is in 3NF but not BCNF.\"],\"formative_assessment\":[\"Solve a BCNF decomposition problem on a given schema.\"],\"engagement\":[\"Debate: Is BCNF always better than 3NF? Discuss the trade-offs.\"]},{\"session_number\":33,\"title\":\"Fourth Normal Form (4NF) & Multivalued Dependencies\",\"duration_mins\":60,\"objectives\":[\"Define multivalued dependencies (MVDs).\",\"Normalize a relation to 4NF by eliminating MVDs.\"],\"teaching_method\":\"Visual modeling and problem-solving\",\"activities\":[\"Identify MVDs in a table containing (Restaurant, Delivery Area, Cuisine Type) and decompose it.\"],\"formative_assessment\":[\"Explain the difference between a functional dependency and a multivalued dependency.\"],\"engagement\":[\"Think-Pair-Share: How do independent multi-valued attributes cause redundancy in a single table?\"]},{\"session_number\":34,\"title\":\"Fifth Normal Form (5NF) & Join Dependencies\",\"duration_mins\":60,\"objectives\":[\"Define join dependencies and Fifth Normal Form (5NF).\",\"Explain scenarios where a relation cannot be reconstructed without 5NF.\"],\"teaching_method\":\"Theoretical lecture with complex visual examples\",\"activities\":[\"Analyze a 3-way relationship that requires 5NF decomposition to avoid join anomalies.\"],\"formative_assessment\":[\"Identify join dependencies in a given scenario.\"],\"engagement\":[\"Group discussion: The practical rarity of 5NF in real-world database design.\"]},{\"session_number\":35,\"title\":\"Decomposition Properties: Lossless-Join Decomposition\",\"duration_mins\":60,\"objectives\":[\"Explain the importance of lossless-join decomposition.\",\"Test a decomposition for the lossless-join property using the matrix method.\"],\"teaching_method\":\"Algorithmic verification and mathematical proof\",\"activities\":[\"Apply Chase algorithm (matrix method) to verify if a decomposition is lossless.\"],\"formative_assessment\":[\"Solve a lossless-join verification problem.\"],\"engagement\":[\"Interactive simulation: Reconstruct a decomposed table to see if \'spurious tuples\' appear.\"]},{\"session_number\":36,\"title\":\"Decomposition Properties: Dependency Preservation\",\"duration_mins\":60,\"objectives\":[\"Define the dependency preservation property.\",\"Test whether a decomposition preserves all functional dependencies.\"],\"teaching_method\":\"Problem-solving workshop and peer instruction\",\"activities\":[\"Compute the projection of FDs on decomposed relations to verify dependency preservation.\"],\"formative_assessment\":[\"Mid-Semester Examination containing analytical normalization and decomposition problems.\"],\"engagement\":[\"Design review: Critique a decomposition that is lossless but fails dependency preservation.\"]},{\"session_number\":37,\"title\":\"Transaction Concepts and States\",\"duration_mins\":60,\"objectives\":[\"Define a transaction in a database context.\",\"Trace a transaction through its lifecycle states (Active, Partially Committed, Committed, Failed, Aborted).\"],\"teaching_method\":\"State-machine tracing and interactive lecture\",\"activities\":[\"Draw and label the transaction state transition diagram.\"],\"formative_assessment\":[\"Identify the state of a transaction given a sequence of database events.\"],\"engagement\":[\"Analogy: Map transaction states to a real-life online shopping checkout process.\"]},{\"session_number\":38,\"title\":\"ACID Properties of Transactions\",\"duration_mins\":60,\"objectives\":[\"Explain Atomicity, Consistency, Isolation, and Durability (ACID).\",\"Identify which DBMS component is responsible for enforcing each ACID property.\"],\"teaching_method\":\"Case-study analysis of financial transaction failures\",\"activities\":[\"Analyze scenarios where a system crash violates Atomicity or Durability.\"],\"formative_assessment\":[\"Match-the-following: ACID properties vs. DBMS components (e.g., Recovery Manager, Concurrency Control).\"],\"engagement\":[\"Debate: Which of the ACID properties is the hardest to guarantee in a distributed system?\"]},{\"session_number\":39,\"title\":\"Schedules and Concurrent Executions\",\"duration_mins\":60,\"objectives\":[\"Define serial and concurrent schedules.\",\"Identify read-write conflicts in concurrent executions.\"],\"teaching_method\":\"Visual tracing of concurrent execution timelines\",\"activities\":[\"Write down the step-by-step execution sequence of two concurrent transactions.\"],\"formative_assessment\":[\"Identify conflicting operations in a given schedule.\"],\"engagement\":[\"Role-play: Two students act as concurrent transactions trying to update the same bank balance.\"]},{\"session_number\":40,\"title\":\"Conflict Serializability\",\"duration_mins\":60,\"objectives\":[\"Define conflict equivalence and conflict serializability.\",\"Construct a precedence graph to test a schedule for conflict serializability.\"],\"teaching_method\":\"Algorithmic graph drawing and problem solving\",\"activities\":[\"Draw precedence graphs for three different schedules and determine if they are conflict serializable.\"],\"formative_assessment\":[\"Quiz on identifying conflict serializable schedules using precedence graphs.\"],\"engagement\":[\"Speed-drawing: Students race to find cycles in complex precedence graphs.\"]},{\"session_number\":41,\"title\":\"View Serializability\",\"duration_mins\":60,\"objectives\":[\"Define view equivalence and view serializability.\",\"Explain the relationship between conflict serializability and view serializability.\"],\"teaching_method\":\"Comparative analysis and logical proofs\",\"activities\":[\"Analyze a schedule that is view serializable but not conflict serializable.\"],\"formative_assessment\":[\"Determine if a given schedule is view serializable using the labeled precedence graph method.\"],\"engagement\":[\"Think-Pair-Share: Why is view serializability rarely used in practice compared to conflict serializability?\"]},{\"session_number\":42,\"title\":\"Recoverability: Cascadeless and Recoverable Schedules\",\"duration_mins\":60,\"objectives\":[\"Define recoverable and cascadeless schedules.\",\"Identify cascading rollbacks in concurrent schedules.\"],\"teaching_method\":\"Visual tracing of rollback scenarios\",\"activities\":[\"Trace a schedule where aborting one transaction forces multiple other transactions to roll back.\"],\"formative_assessment\":[\"Classify a given schedule as recoverable, cascadeless, or non-recoverable.\"],\"engagement\":[\"Interactive simulation: Trace the domino effect of a cascading rollback on a whiteboard.\"]},{\"session_number\":43,\"title\":\"Lock-Based Protocols: Shared and Exclusive Locks\",\"duration_mins\":60,\"objectives\":[\"Explain the lock compatibility matrix.\",\"Apply basic locking (Shared\\/Exclusive) to concurrent transactions.\"],\"teaching_method\":\"Interactive simulation tools for lock-based protocols\",\"activities\":[\"Trace a schedule with lock request and release operations, noting lock grants and waits.\"],\"formative_assessment\":[\"Determine if a lock request will be granted or queued based on the current lock table.\"],\"engagement\":[\"Locking game: Students use colored cards (Red for Exclusive, Green for Shared) to request access to resources.\"]},{\"session_number\":44,\"title\":\"Two-Phase Locking (2PL) Protocol\",\"duration_mins\":60,\"objectives\":[\"Explain the growing and shrinking phases of the Two-Phase Locking (2PL) protocol.\",\"Prove that 2PL guarantees conflict serializability.\"],\"teaching_method\":\"Mathematical proof and visual tracing\",\"activities\":[\"Convert a non-2PL schedule into a 2PL-compliant schedule by inserting lock\\/unlock points.\"],\"formative_assessment\":[\"Identify violations of the 2PL protocol in a given transaction execution.\"],\"engagement\":[\"Peer instruction: Explain to your partner why releasing a lock early prevents 2PL compliance.\"]},{\"session_number\":45,\"title\":\"Strict 2PL and Rigorous 2PL\",\"duration_mins\":60,\"objectives\":[\"Differentiate between Basic 2PL, Strict 2PL, and Rigorous 2PL.\",\"Explain how Strict 2PL guarantees cascadeless recovery.\"],\"teaching_method\":\"Comparative analysis and timeline tracing\",\"activities\":[\"Trace concurrent transactions under Strict 2PL and observe how cascading rollbacks are prevented.\"],\"formative_assessment\":[\"Solve a worksheet comparing the concurrency levels allowed by Basic, Strict, and Rigorous 2PL.\"],\"engagement\":[\"Discussion: Why do modern commercial databases prefer Strict 2PL over Basic 2PL?\"]},{\"session_number\":46,\"title\":\"Deadlocks in Databases: Prevention and Detection\",\"duration_mins\":60,\"objectives\":[\"Explain how deadlocks occur in lock-based protocols.\",\"Apply deadlock prevention strategies (Wait-Die, Wound-Wait) and detection using Wait-For Graphs (WFG).\"],\"teaching_method\":\"Algorithmic dry-runs and graph analysis\",\"activities\":[\"Draw a Wait-For Graph for a deadlocked system and identify the victim transaction to abort.\"],\"formative_assessment\":[\"Analytical problem-solving test on Deadlock detection algorithms.\"],\"engagement\":[\"Interactive simulation: Run a live deadlock scenario and let students decide which transaction to \'kill\' to resolve it.\"]},{\"session_number\":47,\"title\":\"Timestamp-Based Protocols\",\"duration_mins\":60,\"objectives\":[\"Explain the concept of transaction timestamps.\",\"Apply the Timestamp Ordering protocol to order read and write operations.\"],\"teaching_method\":\"Step-by-step algorithmic tracing\",\"activities\":[\"Trace a sequence of read\\/write requests using read-timestamps and write-timestamps on data items.\"],\"formative_assessment\":[\"Determine if a read or write operation will be rejected\\/rolled back under the Timestamp Ordering protocol.\"],\"engagement\":[\"Timeline tracing: Use colored markers to track read\\/write timestamps on a shared timeline.\"]},{\"session_number\":48,\"title\":\"Thomas\' Write Rule & Validation-Based Protocols\",\"duration_mins\":60,\"objectives\":[\"Apply Thomas\' Write Rule to optimize timestamp-based concurrency control.\",\"Describe the phases of Validation-Based (Optimistic) concurrency control.\"],\"teaching_method\":\"Comparative analysis and algorithmic walkthroughs\",\"activities\":[\"Trace a schedule where Thomas\' Write Rule allows a write operation that would otherwise be rejected.\"],\"formative_assessment\":[\"Compare optimistic vs. pessimistic concurrency control in a short written summary.\"],\"engagement\":[\"Debate: When should you use Validation-Based protocols instead of Lock-Based protocols?\"]},{\"session_number\":49,\"title\":\"Storage Architecture: File and Record Organization\",\"duration_mins\":60,\"objectives\":[\"Describe the physical storage hierarchy (magnetic disks, SSDs, main memory).\",\"Compare fixed-length and variable-length record organizations.\"],\"teaching_method\":\"Architectural diagrams and comparative analysis\",\"activities\":[\"Calculate the block utilization and span for a given record size and block size.\"],\"formative_assessment\":[\"Short quiz on calculating record offsets in a variable-length record block.\"],\"engagement\":[\"Analogy: Comparing database blocks and records to shipping containers and boxes.\"]},{\"session_number\":50,\"title\":\"File Organizations & RAID Levels\",\"duration_mins\":60,\"objectives\":[\"Compare Heap, Sequential, and Hashing file organizations.\",\"Analyze RAID levels (RAID 0, 1, 5, 10) for performance and fault tolerance.\"],\"teaching_method\":\"Comparative analysis using real-world performance metrics\",\"activities\":[\"Select the optimal RAID level for a read-heavy database vs. a write-heavy transaction log.\"],\"formative_assessment\":[\"Fill out a comparison matrix of RAID levels based on cost, read speed, write speed, and redundancy.\"],\"engagement\":[\"Disaster simulation: \'Drive failure!\' Students calculate if data is lost under different RAID configurations.\"]},{\"session_number\":51,\"title\":\"Indexing Concepts: Primary, Secondary, and Clustered Indexes\",\"duration_mins\":60,\"objectives\":[\"Explain the purpose of database indexing.\",\"Differentiate between primary, secondary, and clustered indexes.\"],\"teaching_method\":\"Visual diagramming and conceptual walkthroughs\",\"activities\":[\"Draw the pointer structures for a primary index and a secondary index on a sample data file.\"],\"formative_assessment\":[\"Identify which index type is best suited for a given search key and file organization.\"],\"engagement\":[\"Book index analogy: Comparing a primary index to a book\'s table of contents and a secondary index to the back-of-the-book index.\"]},{\"session_number\":52,\"title\":\"Sparse vs. Dense Indexes & Multi-level Indexing\",\"duration_mins\":60,\"objectives\":[\"Compare sparse and dense index structures in terms of space and lookup time.\",\"Explain the necessity of multi-level indexing for large databases.\"],\"teaching_method\":\"Step-by-step mathematical calculations\",\"activities\":[\"Calculate the number of block accesses required to find a record with and without a multi-level index.\"],\"formative_assessment\":[\"Solve a problem on calculating the size of a index file given record and block sizes.\"],\"engagement\":[\"Think-Pair-Share: Why can\'t we have a sparse index on a non-ordered file?\"]},{\"session_number\":53,\"title\":\"Static and Dynamic Hashing\",\"duration_mins\":60,\"objectives\":[\"Explain bucket overflow and collision resolution in static hashing.\",\"Implement dynamic hashing techniques (Extendible Hashing).\"],\"teaching_method\":\"Algorithmic dry-runs and interactive tracing\",\"activities\":[\"Perform step-by-step directory doubling and bucket splitting in Extendible Hashing.\"],\"formative_assessment\":[\"Draw the state of an Extendible Hash structure after inserting a sequence of keys.\"],\"engagement\":[\"Interactive board work: Students take turns inserting keys and splitting buckets.\"]},{\"session_number\":54,\"title\":\"Tree-Structured Indexing: B-Trees\",\"duration_mins\":60,\"objectives\":[\"Describe the structural properties of a B-Tree.\",\"Perform search and insertion operations in a B-Tree.\"],\"teaching_method\":\"Animation-based demonstration and step-by-step drawing\",\"activities\":[\"Construct a B-Tree of order 3 by inserting a sequence of integer keys.\"],\"formative_assessment\":[\"Identify invalid B-Tree structures based on node occupancy rules.\"],\"engagement\":[\"Visual tracing: Students use an online interactive B-Tree visualization tool to verify their hand-drawn steps.\"]},{\"session_number\":55,\"title\":\"Tree-Structured Indexing: B+ Trees\",\"duration_mins\":60,\"objectives\":[\"Differentiate between B-Trees and B+ Trees.\",\"Perform insertion and deletion operations in a B+ Tree.\"],\"teaching_method\":\"Step-by-step tree modification exercises\",\"activities\":[\"Insert and delete keys from a B+ Tree, demonstrating node splitting and merging.\"],\"formative_assessment\":[\"Assignment on B+ Tree dry-runs (insertion and deletion steps).\"],\"engagement\":[\"Peer check: Exchange hand-drawn B+ Trees after a deletion operation and verify node pointers.\"]},{\"session_number\":56,\"title\":\"Database Recovery: Failure Classification\",\"duration_mins\":60,\"objectives\":[\"Classify types of database failures (Transaction, System, Disk).\",\"Explain the role of the recovery manager in maintaining consistency.\"],\"teaching_method\":\"Interactive lecture and failure scenario mapping\",\"activities\":[\"Map real-world disaster scenarios (power cut, bad sector, software bug) to database failure classes.\"],\"formative_assessment\":[\"Short quiz on choosing the correct recovery strategy for different failure types.\"],\"engagement\":[\"Brainstorming: What happens to active transactions when the power plug is pulled?\"]},{\"session_number\":57,\"title\":\"Log-Based Recovery: Deferred Database Modification\",\"duration_mins\":60,\"objectives\":[\"Explain the concept of write-ahead logging (WAL).\",\"Apply the Deferred Database Modification recovery algorithm after a system crash.\"],\"teaching_method\":\"Step-by-step walkthrough of log-based recovery scenarios\",\"activities\":[\"Analyze a sample transaction log and determine which transactions to REDO and which to ignore.\"],\"formative_assessment\":[\"Write the recovery actions (REDO list) for a given log file and crash point.\"],\"engagement\":[\"Role-play: One student acts as the log writer, another as the database disk, coordinating writes.\"]},{\"session_number\":58,\"title\":\"Log-Based Recovery: Immediate Database Modification\",\"duration_mins\":60,\"objectives\":[\"Explain the immediate database modification technique.\",\"Apply UNDO and REDO operations to restore database consistency.\"],\"teaching_method\":\"Trace-based problem solving\",\"activities\":[\"Trace a recovery process requiring both UNDO and REDO operations from a log file.\"],\"formative_assessment\":[\"Construct the UNDO and REDO lists for a given immediate modification log.\"],\"engagement\":[\"Interactive challenge: Find the mistake in a flawed UNDO\\/REDO recovery trace.\"]},{\"session_number\":59,\"title\":\"Checkpoints in Recovery Systems\",\"duration_mins\":60,\"objectives\":[\"Explain how checkpoints reduce recovery overhead.\",\"Perform recovery analysis on a log containing checkpoint records.\"],\"teaching_method\":\"Visual timeline analysis and problem-solving\",\"activities\":[\"Analyze a timeline of active transactions intersecting with a checkpoint and a system crash.\"],\"formative_assessment\":[\"Determine the starting point of log scanning during recovery when a checkpoint is present.\"],\"engagement\":[\"Think-Pair-Share: Why can\'t we just checkpoint after every single transaction?\"]},{\"session_number\":60,\"title\":\"Shadow Paging & Recovery Review\",\"duration_mins\":60,\"objectives\":[\"Describe the shadow paging recovery technique.\",\"Compare log-based recovery with shadow paging.\"],\"teaching_method\":\"Comparative review and course wrap-up\",\"activities\":[\"Draw the directory structures of current and shadow page tables during a transaction.\"],\"formative_assessment\":[\"End-Semester mock questions on Log-based recovery, Checkpointing, and Shadow Paging.\"],\"engagement\":[\"Jeopardy-style review game covering the key concepts of the entire course.\"]}]}', 'gemini-3.5-flash', NULL, NULL, 58403, 'success', NULL, 'course_plan', 1, '2026-09-19 07:57:42'),
(4, 1, 3, 'questions', 'questions', '{\"type\":\"mcq\",\"unit\":1,\"klevel\":\"K2\",\"count\":10,\"subject\":\"Database Management Systems\",\"provider\":\"gemini\"}', '{\"questions\":[{\"stem\":\"A database administrator decides to change the storage structure of a database from B-trees to hashing to improve query performance. If the conceptual schema and external views of the database remain completely unaffected by this change, which concept is being demonstrated?\",\"options\":{\"A\":\"Logical Data Independence\",\"B\":\"Physical Data Independence\",\"C\":\"Schema Evolution\",\"D\":\"View Materialization\"},\"correct_answer\":\"B\",\"explanation\":\"Physical data independence is the ability to modify the physical schema (such as storage structures, file organizations, or indexes) without requiring changes to the conceptual or external schemas.\",\"marks\":1,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"In a traditional file-processing system, the same customer address might be stored in both a billing file and a shipping file. If the customer moves and only the billing file is updated, this leads to inconsistent data. How does a Database Management System (DBMS) primarily resolve this issue?\",\"options\":{\"A\":\"By storing data in multiple redundant files and synchronizing them periodically using background batch processes.\",\"B\":\"By centralizing data storage so that a single logical representation of data is shared, minimizing redundancy and enforcing integrity constraints.\",\"C\":\"By converting all data into unstructured formats that do not require schema definitions or updates.\",\"D\":\"By forcing the application layer to handle all data validation and synchronization logic.\"},\"correct_answer\":\"B\",\"explanation\":\"A DBMS reduces data redundancy by integrating files into a single logical database, ensuring that updates to a data item are reflected across all views, thereby maintaining consistency.\",\"marks\":1,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"Which of the following scenarios best illustrates the role of the conceptual schema within the Three-Schema Architecture?\",\"options\":{\"A\":\"A database designer defines the entities, relationships, constraints, and security rules for the entire enterprise database without specifying physical storage details.\",\"B\":\"A database programmer designs a customized user interface that displays only a subset of employee records based on department.\",\"C\":\"A system administrator configures the disk allocation, indexing strategies, and block sizes for the database files.\",\"D\":\"A data analyst writes an ad-hoc SQL query to extract monthly sales figures for a presentation.\"},\"correct_answer\":\"A\",\"explanation\":\"The conceptual schema describes the structure of the entire database for a community of users, focusing on entities, relationships, and constraints while hiding physical storage details.\",\"marks\":1,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"A database developer executes a command that alters the structure of an existing table by adding a new column for email addresses. What type of database language is being used, and what is its primary effect on the database?\",\"options\":{\"A\":\"Data Manipulation Language (DML); it updates the actual data values stored in the table rows.\",\"B\":\"Data Control Language (DCL); it modifies the access privileges of users who can view the table.\",\"C\":\"Data Definition Language (DDL); it updates the database schema stored in the data dictionary.\",\"D\":\"Transaction Control Language (TCL); it ensures that the structural change is committed atomically.\"},\"correct_answer\":\"C\",\"explanation\":\"DDL commands (like ALTER TABLE) are used to define or modify the database schema, and their metadata is stored in the system catalog or data dictionary.\",\"marks\":1,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"In an ER model for a university, the \'Student\' entity set has attributes: Student_ID (unique), Email (unique), SSN (unique), Name, and Date_of_Birth. Which of the following statements correctly explains the relationship between candidate keys and the primary key for this entity set?\",\"options\":{\"A\":\"Only Student_ID is a candidate key, and it must be chosen as the primary key.\",\"B\":\"Name and Date_of_Birth together form the only candidate key because they represent real-world attributes.\",\"C\":\"All attributes combined form a single candidate key, from which the primary key is extracted.\",\"D\":\"Student_ID, Email, and SSN are all candidate keys, and any one of them can be selected as the primary key.\"},\"correct_answer\":\"D\",\"explanation\":\"Candidate keys are minimal superkeys that uniquely identify an entity. Since Student_ID, Email, and SSN are all unique, they are all candidate keys, and the database designer can choose any one of them to be the primary key.\",\"marks\":1,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"Consider a relationship \'Manages\' between entity sets \'Manager\' and \'Department\'. If each manager can manage at most one department, and each department must be managed by exactly one manager, what is the mapping cardinality of this relationship?\",\"options\":{\"A\":\"One-to-One (1:1)\",\"B\":\"One-to-Many (1:N)\",\"C\":\"Many-to-One (N:1)\",\"D\":\"Many-to-Many (M:N)\"},\"correct_answer\":\"A\",\"explanation\":\"Since a manager manages at most one department (1) and a department is managed by exactly one manager (1), the mapping cardinality is 1:1.\",\"marks\":1,\"difficulty\":\"easy\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"An organization models its workforce by first identifying a general entity set \'Employee\' and then distinguishing subgroups \'Hourly_Employee\' and \'Salaried_Employee\' based on their payment structures. Which modeling process does this scenario represent, and what is its primary characteristic?\",\"options\":{\"A\":\"Generalization; it is a bottom-up process that combines low-level entity sets into a high-level entity set.\",\"B\":\"Specialization; it is a top-down process that designates sub-groupings within an entity set based on distinguishing features.\",\"C\":\"Aggregation; it treats a relationship set as a high-level entity set to relate it to other entities.\",\"D\":\"Realization; it maps abstract logical schemas directly to physical storage structures.\"},\"correct_answer\":\"B\",\"explanation\":\"Specialization is a top-down process of identifying lower-level subgroups (Hourly_Employee, Salaried_Employee) within a higher-level entity set (Employee) based on specific characteristics.\",\"marks\":1,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"In an ER diagram, we want to model that an \'Employee\' works on a \'Project\', and this entire interaction requires the use of specific \'Equipment\'. Why is \'Aggregation\' used in this scenario instead of a simple ternary relationship?\",\"options\":{\"A\":\"To represent that the \'Equipment\' entity set is a subclass of both \'Employee\' and \'Project\'.\",\"B\":\"To avoid redundancy by treating the relationship between \'Employee\' and \'Project\' as an abstract entity that can participate in a relationship with \'Equipment\'.\",\"C\":\"To enforce a constraint that an employee cannot work on a project unless they already own the equipment.\",\"D\":\"To convert a many-to-many relationship into two one-to-many relationships for easier database implementation.\"},\"correct_answer\":\"B\",\"explanation\":\"Aggregation is an abstraction through which relationships are treated as higher-level entities. It allows us to model a relationship between an entity set and another relationship set (e.g., relating \'Equipment\' to the \'Works-on\' relationship between \'Employee\' and \'Project\').\",\"marks\":1,\"difficulty\":\"hard\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"When converting an ER diagram to relational tables, how is a many-to-many (M:N) relationship set \'Enrolls\' between entity sets \'Student\' (primary key: Student_ID) and \'Course\' (primary key: Course_ID) typically represented?\",\"options\":{\"A\":\"By adding Course_ID as a foreign key in the \'Student\' table.\",\"B\":\"By adding Student_ID as a foreign key in the \'Course\' table.\",\"C\":\"By creating a new table \'Enrolls\' whose primary key consists of the combination of Student_ID and Course_ID as foreign keys.\",\"D\":\"By merging the \'Student\' and \'Course\' tables into a single unified table with a composite primary key.\"},\"correct_answer\":\"C\",\"explanation\":\"An M:N relationship requires a separate junction table containing the primary keys of both participating entity sets as foreign keys. The combination of these foreign keys typically forms the primary key of the new table.\",\"marks\":1,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"Which of the following tasks is a primary responsibility of a Database Administrator (DBA) rather than an application programmer or a naive end-user?\",\"options\":{\"A\":\"Authorizing access to the database, coordinating and monitoring its use, and acquiring software\\/hardware resources.\",\"B\":\"Writing parameterized SQL queries embedded in a Java application to retrieve customer profiles.\",\"C\":\"Entering daily sales transactions through a pre-designed web form interface.\",\"D\":\"Generating ad-hoc analytical reports using a spreadsheet tool connected to a read-only database view.\"},\"correct_answer\":\"A\",\"explanation\":\"The DBA is responsible for authorizing access to the database, coordinating and monitoring its use, and acquiring software and hardware resources as needed.\",\"marks\":1,\"difficulty\":\"easy\",\"bloom_k_level\":\"K2\",\"unit_number\":1}]}', 'gemini-3.5-flash', NULL, NULL, 31985, 'success', NULL, 'question_bank', 1, '2026-09-19 07:58:50');
INSERT INTO `ai_generations` (`id`, `institution_id`, `user_id`, `module`, `prompt_code`, `input_payload`, `output_payload`, `model`, `tokens_in`, `tokens_out`, `latency_ms`, `status`, `error_message`, `ref_type`, `ref_id`, `created_at`) VALUES
(5, 1, 3, 'assignment', 'assignment', '{\"type\":\"case_study\",\"subject\":\"Database Management Systems\",\"class_id\":1,\"provider\":\"gemini\"}', '{\"title\":\"Case Study Assignment: E-Commerce Database Optimization and Normalization\",\"description\":\"Students are required to analyze a flawed relational database schema for a rapidly growing e-commerce platform, identify anomalies, redesign the schema up to Boyce-Codd Normal Form (BCNF), write complex SQL queries for business analytics, and propose indexing strategies for performance optimization.\",\"instructions\":[\"Read the provided case study scenario detailing the \'ShopSmart\' e-commerce database schema and its current performance bottlenecks.\",\"Part A: Identify and document functional dependencies and anomalies (insertion, update, deletion) present in the unnormalized tables.\",\"Part B: Normalize the database schema progressively from 1NF up to BCNF, showing all intermediate steps.\",\"Part C: Write optimized SQL queries to generate specific monthly sales reports, customer segmentation insights, and inventory alerts.\",\"Part D: Propose suitable indexes and query execution plan improvements to resolve reported slow-loading dashboard issues.\",\"Submit a comprehensive PDF report containing the schema diagrams, SQL script outputs, and analytical explanations.\"],\"max_marks\":50,\"rubric\":[{\"criterion\":\"Functional Dependencies and Anomaly Analysis\",\"description\":\"Accurately identifies functional dependencies and clearly explains update, insertion, and deletion anomalies in the given schema.\",\"marks\":10,\"clo\":\"CLO1\",\"bloom\":\"K2\",\"levels\":[\"Poor (0-3 marks): Fails to identify core functional dependencies or anomalies.\",\"Average (4-7 marks): Identifies some dependencies but misses critical anomalies.\",\"Excellent (8-10 marks): Comprehensively documents all functional dependencies and clearly explains all relation anomalies.\"]},{\"criterion\":\"Schema Normalization (Up to BCNF)\",\"description\":\"Successfully decomposes relations to eliminate redundancy and achieves Boyce-Codd Normal Form (BCNF) while preserving dependencies.\",\"marks\":15,\"clo\":\"CLO2\",\"bloom\":\"K3\",\"levels\":[\"Poor (0-5 marks): Normalization stops prematurely or violates lossless join properties.\",\"Average (6-10 marks): Reaches 3NF but fails to achieve BCNF correctly.\",\"Excellent (11-15 marks): Flawlessly normalizes schemas up to BCNF with proper primary and foreign key definitions.\"]},{\"criterion\":\"Advanced SQL Query Formulation\",\"description\":\"Writes efficient and accurate SQL queries involving multi-table joins, subqueries, group by clauses, and window functions.\",\"marks\":15,\"clo\":\"CLO3\",\"bloom\":\"K3\",\"levels\":[\"Poor (0-5 marks): Queries contain syntax errors or fail to yield the required business insights.\",\"Average (6-10 marks): Queries work for basic requirements but lack efficiency or complex grouping logic.\",\"Excellent (11-15 marks): Demonstrates mastery in writing complex, optimized SQL queries returning accurate analytical datasets.\"]},{\"criterion\":\"Indexing and Performance Optimization\",\"description\":\"Proposes appropriate indexing strategies (B-Trees, composite indexes) and interprets query execution plans to resolve performance issues.\",\"marks\":10,\"clo\":\"CLO4\",\"bloom\":\"K3\",\"levels\":[\"Poor (0-3 marks): Suggests random indexes without justification or understanding of execution plans.\",\"Average (4-7 marks): Proposes basic single-column indexes with partial justification.\",\"Excellent (8-10 marks): Strategically recommends composite and covering indexes based on workload analysis and query execution bottlenecks.\"]}]}', 'gemini-3.5-flash-lite', NULL, NULL, 4840, 'success', NULL, 'assignment', 1, '2026-09-19 08:04:56'),
(6, 1, 5, 'course_plan', 'course_plan', '{\"subject\":\"Operating Systems\",\"credits\":\"4\",\"university\":\"Autonomous\",\"template\":\"standard\",\"provider\":\"gemini\"}', '{\"title\":\"Operating Systems\",\"learning_outcomes\":[\"CO1: Explain the fundamental structures, components, and evolution of modern operating systems.\",\"CO2: Analyze and implement CPU scheduling, process synchronization, and deadlock prevention\\/avoidance algorithms.\",\"CO3: Evaluate memory management techniques, including paging, segmentation, and virtual memory page replacement policies.\",\"CO4: Design and analyze file systems, directory structures, disk scheduling algorithms, and I\\/O management techniques.\",\"CO5: Compare virtualization architectures, hypervisors, and mobile operating system security models.\",\"CO6: Develop system-level programs to simulate operating system concepts such as scheduling, synchronization, and memory allocation.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to Operating Systems\",\"hours\":11,\"topics\":[\"Computer System Overview: Elements, organization, architecture, and multi-core systems.\",\"Operating System Basics: Objectives, functions, and historical evolution of operating systems.\",\"System Structures: OS services, user interface, system calls, and system programs.\",\"Design and Implementation: Structuring methods and virtual machines introduction.\"],\"outcomes\":[\"Identify the core components and architectural elements of a computer system.\",\"Explain the services, functions, and structural design of modern operating systems.\",\"Differentiate between system calls and system programs, and understand their execution flow.\"],\"bloom_k_level\":\"K2\",\"teaching_methods\":[\"Chalk and Talk\",\"PowerPoint Presentations\",\"Collaborative Learning (Think-Pair-Share on System Calls)\"],\"assessment\":[\"Class Test on OS Structures\",\"Assignment on System Calls tracing in Linux\"]},{\"unit_number\":2,\"title\":\"Process Management & Synchronization\",\"hours\":14,\"topics\":[\"Process Concept: Process scheduling, operations on processes, and Inter-Process Communication (IPC).\",\"CPU Scheduling: Scheduling criteria and scheduling algorithms (FCFS, SJF, Priority, Round Robin).\",\"Threads: Multithreaded models and threading issues.\",\"Process Synchronization: The critical-section problem, synchronization hardware, semaphores, mutex locks, and classical synchronization problems.\",\"Deadlocks: Characterization, methods for handling deadlocks, prevention, avoidance, detection, and recovery.\"],\"outcomes\":[\"Analyze and compare various CPU scheduling algorithms based on performance criteria.\",\"Solve classical synchronization problems using semaphores and mutex locks.\",\"Apply Banker\'s algorithm for deadlock avoidance and analyze deadlock recovery strategies.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Interactive Lectures\",\"Problem-Solving Sessions (Scheduling & Deadlocks)\",\"Coding Demonstrations (POSIX Threads in C)\"],\"assessment\":[\"Mid-Term Examination\",\"Programming Assignment: Simulation of CPU Scheduling Algorithms\",\"Quiz on Process Synchronization\"]},{\"unit_number\":3,\"title\":\"Memory Management\",\"hours\":12,\"topics\":[\"Main Memory: Background, swapping, contiguous memory allocation, and paging.\",\"Page Tables & Segmentation: Structure of page tables and segmentation with paging.\",\"Virtual Memory: Demand paging, copy-on-write, page replacement algorithms (FIFO, LRU, Optimal), and allocation of frames.\",\"Performance: Thrashing and memory-mapped files.\"],\"outcomes\":[\"Contrast contiguous and non-contiguous memory allocation techniques.\",\"Calculate physical addresses from logical addresses using paging and segmentation schemes.\",\"Evaluate and compare the performance of various page replacement algorithms.\"],\"bloom_k_level\":\"K5\",\"teaching_methods\":[\"Flipped Classroom (Paging concepts)\",\"Analytical Problem Solving (Address Translation)\",\"Visual Demonstrations of Page Replacement\"],\"assessment\":[\"Analytical Assignment on Address Translation and Page Tables\",\"Simulation Project: Page Replacement Algorithms\",\"In-class Problem Solving Test\"]},{\"unit_number\":4,\"title\":\"Storage Management & I\\/O Systems\",\"hours\":12,\"topics\":[\"Mass-Storage Structure: Disk structure, disk scheduling algorithms, and disk management.\",\"File System Interface: File concept, access methods, directory structure, file system mounting, sharing, and protection.\",\"File System Implementation: File-system structure, directory implementation, allocation methods (contiguous, linked, indexed), and free-space management.\",\"I\\/O Systems: I\\/O hardware, application I\\/O interfaces, and kernel I\\/O subsystems.\"],\"outcomes\":[\"Analyze and implement disk scheduling algorithms to optimize I\\/O performance.\",\"Compare different directory structures and file allocation methods.\",\"Explain the role of kernel I\\/O subsystems, buffering, and caching.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Lectures\",\"Case Studies of Ext4 and NTFS File Systems\",\"Group Discussion on Disk Scheduling Efficiency\"],\"assessment\":[\"Home Assignment on File Allocation Methods\",\"Written Test on Disk Scheduling Algorithms\",\"Viva-voce on File System Implementation\"]},{\"unit_number\":5,\"title\":\"Virtualization and Mobile Operating Systems\",\"hours\":11,\"topics\":[\"Virtual Machines: History, benefits, features, building blocks, and types of virtual machines and their implementations.\",\"Virtualization: OS components and support for virtualization.\",\"Mobile Operating Systems: Architecture, features, and security models of modern mobile operating systems (focused on Android and iOS).\"],\"outcomes\":[\"Distinguish between Type-1 and Type-2 hypervisors and their deployment scenarios.\",\"Explain the architectural differences between desktop\\/server OS and mobile OS.\",\"Analyze the security models and permission frameworks of Android and iOS.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Technical Seminars\",\"Comparative Case Studies (Android vs. iOS)\",\"Hands-on Demo of VirtualBox\\/VMware\"],\"assessment\":[\"Technical Presentation on Virtualization Technologies\",\"Comparative Report on Mobile OS Security Models\",\"End-Semester Theory Examination\"]}],\"weekly_plan\":[{\"week\":1,\"topics\":[\"Computer System Elements, Organization, and Architecture\",\"Multi-core Systems and Hardware Support\"],\"pedagogy\":\"Chalk & Talk, Interactive Discussion\"},{\"week\":2,\"topics\":[\"OS Objectives, Functions, and Evolution\",\"OS Services and User Interfaces\"],\"pedagogy\":\"PowerPoint Presentation, Case Study of Early OS\"},{\"week\":3,\"topics\":[\"System Calls, System Programs, and OS Structuring Methods\",\"Introduction to Virtual Machines\"],\"pedagogy\":\"Demonstration of Linux System Calls (strace)\"},{\"week\":4,\"topics\":[\"Process Concept, Process Control Block (PCB), and Scheduling Queues\",\"Operations on Processes and Inter-Process Communication (IPC)\"],\"pedagogy\":\"Collaborative Learning, Coding IPC (Pipes\\/Shared Memory)\"},{\"week\":5,\"topics\":[\"CPU Scheduling Criteria and Non-preemptive Algorithms (FCFS, SJF)\",\"Preemptive Algorithms (SJF, Priority, Round Robin)\"],\"pedagogy\":\"Problem Solving, Algorithm Simulation\"},{\"week\":6,\"topics\":[\"Multithreaded Models, Threading Issues, and Pthreads Library\",\"The Critical-Section Problem and Synchronization Hardware\"],\"pedagogy\":\"Hands-on Lab on Pthreads, Analytical Discussion\"},{\"week\":7,\"topics\":[\"Semaphores, Mutex Locks, and Classical Synchronization Problems\",\"Deadlock Characterization, Prevention, and Avoidance (Banker\'s Algorithm)\"],\"pedagogy\":\"Role Play for Dining Philosophers, Problem Solving\"},{\"week\":8,\"topics\":[\"Deadlock Detection and Recovery\",\"Main Memory Background, Swapping, and Contiguous Allocation\"],\"pedagogy\":\"Interactive Lecture, Numerical Exercises\"},{\"week\":9,\"topics\":[\"Paging: Basic Method, Hardware Support, and Page Table Structure\",\"Segmentation and Segmentation with Paging\"],\"pedagogy\":\"Visual Address Translation Exercises, Flipped Classroom\"},{\"week\":10,\"topics\":[\"Virtual Memory: Demand Paging and Copy-on-Write\",\"Page Replacement Algorithms (FIFO, LRU, Optimal)\"],\"pedagogy\":\"Algorithm Comparison, Graphical Trace Analysis\"},{\"week\":11,\"topics\":[\"Allocation of Frames, Thrashing, and Memory-Mapped Files\",\"Mass-Storage Structure and Disk Scheduling Algorithms\"],\"pedagogy\":\"Problem Solving, Simulation of Disk Scheduling\"},{\"week\":12,\"topics\":[\"Disk Management and Swap-Space Management\",\"File Concept, Access Methods, and Directory Structures\"],\"pedagogy\":\"Case Study of Disk Partitioning, Lectures\"},{\"week\":13,\"topics\":[\"File System Mounting, Protection, and Implementation Structures\",\"Directory Implementation and File Allocation Methods\"],\"pedagogy\":\"Comparative Analysis of Allocation Schemes\"},{\"week\":14,\"topics\":[\"Free-Space Management, I\\/O Hardware, and Kernel I\\/O Subsystem\",\"Virtual Machines: History, Benefits, and Types of Hypervisors\"],\"pedagogy\":\"Technical Seminar, Conceptual Mapping\"},{\"week\":15,\"topics\":[\"OS Support for Virtualization\",\"Mobile OS Architecture, Features, and Android\\/iOS Security Models\"],\"pedagogy\":\"Comparative Case Study, Group Presentation\"}],\"resources\":[{\"type\":\"Textbook\",\"details\":\"Abraham Silberschatz, Peter Baer Galvin, and Greg Gagne, \'Operating System Concepts\', 10th Edition, John Wiley & Sons, 2018.\"},{\"type\":\"Textbook\",\"details\":\"William Stallings, \'Operating Systems: Internals and Design Principles\', 9th Edition, Pearson Education, 2018.\"},{\"type\":\"Reference Book\",\"details\":\"Andrew S. Tanenbaum and Herbert Bos, \'Modern Operating Systems\', 4th Edition, Pearson, 2015.\"},{\"type\":\"Online Course\",\"details\":\"NPTEL: \'Operating Systems\' by Prof. Santanu Chattopadhyay, IIT Kharagpur.\"},{\"type\":\"Online Course\",\"details\":\"MIT OpenCourseWare: \'Operating System Engineering\' (6.828).\"}],\"expert_advice\":[\"Integrate a mandatory laboratory component using Linux\\/Unix environment to write C programs for system calls, process creation, IPC, and thread synchronization.\",\"Emphasize the practical implications of race conditions and deadlocks using real-world multi-threaded application bugs.\",\"Introduce modern containerization concepts (like Docker) briefly under the virtualization unit to align with current industry standards.\",\"Utilize visualization tools for memory management and disk scheduling to help students grasp physical-to-logical mappings easily.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":25,\"K4\":25,\"K5\":15,\"K6\":5},\"ai_score\":1.95}', 'gemini-3.5-flash', NULL, NULL, 72685, 'success', NULL, NULL, NULL, '2026-09-29 04:15:44'),
(7, 1, 5, 'bloom', 'bloom', '{\"plan_id\":2,\"provider\":\"gemini\"}', '{\"units\":[{\"unit_number\":1,\"title\":\"Introduction to Operating Systems\",\"bloom_k_level\":\"K2\"},{\"unit_number\":2,\"title\":\"Process Management & Synchronization\",\"bloom_k_level\":\"K4\"},{\"unit_number\":3,\"title\":\"Memory Management\",\"bloom_k_level\":\"K5\"},{\"unit_number\":4,\"title\":\"Storage Management & I\\/O Systems\",\"bloom_k_level\":\"K4\"},{\"unit_number\":5,\"title\":\"Virtualization and Mobile Operating Systems\",\"bloom_k_level\":\"K4\"}],\"distribution_percentages\":{\"K1\":10,\"K2\":20,\"K3\":25,\"K4\":25,\"K5\":15,\"K6\":5}}', 'gemini-3.5-flash-lite', NULL, NULL, 30831, 'success', NULL, 'course_plan', 2, '2026-09-29 04:16:58'),
(8, 1, 5, 'assignment', 'assignment', '{\"type\":\"case_study\",\"subject\":\"Operating Systems\",\"class_id\":1,\"provider\":\"gemini\"}', '{\"title\":\"Case Study: Architectural Analysis and Kernel-Mode Transition Failures in Enterprise Operating Systems\",\"description\":\"Analyze a real-world scenario involving a mission-critical server experiencing performance degradation and unauthorized privilege escalation due to flawed system call handling and improper hardware-level dual-mode enforcement. Students will investigate the core operating system architecture, distinguish between user and kernel mode operations, trace interrupt\\/trap execution paths, and evaluate the trade-offs between monolithic and microkernel architectures.\",\"instructions\":[\"Read the provided case study scenario detailing the dual-mode transition failure and system call bottlenecks in an enterprise server environment.\",\"Identify the foundational operating system components involved in hardware abstraction, resource management, and system call dispatching.\",\"Diagram and trace the complete lifecycle of a system call from user-space execution, mode-bit transition, trap handling, to kernel-space service routine execution.\",\"Evaluate the security implications of user\\/kernel mode boundary breaches and propose remediation using appropriate OS architectural paradigms.\",\"Submit your detailed analytical report in standard format (PDF) within the designated deadline adhering to academic integrity standards.\"],\"max_marks\":25,\"rubric\":[{\"criterion\":\"Identification of OS Components and Core Services\",\"description\":\"Accurately identify and explain the role of fundamental OS components (process management, memory management, storage abstraction, and protection subsystems) relevant to the case.\",\"marks\":5,\"clo\":\"CLO1\",\"bloom\":\"K2\",\"levels\":{\"Excellent (5 marks)\":\"Thoroughly identifies and explains all relevant OS components and services with precise operational context.\",\"Good (3-4 marks)\":\"Identifies most OS components with minor gaps in operational role explanations.\",\"Developing (1-2 marks)\":\"Superficial identification of basic OS components with limited contextual connection.\",\"Unsatisfactory (0 marks)\":\"Fails to identify relevant OS components or services.\"}},{\"criterion\":\"Dual-Mode Execution & Privilege Boundary Analysis\",\"description\":\"Analyze the mechanics of dual-mode operation (User Mode vs. Kernel Mode), hardware mode bit manipulation, and the root cause of the privilege escalation vulnerability.\",\"marks\":7,\"clo\":\"CLO1\",\"bloom\":\"K3\",\"levels\":{\"Excellent (6-7 marks)\":\"Provides an in-depth technical analysis of dual-mode mechanisms, hardware mode bit toggling, and exact vulnerability exploitation paths.\",\"Good (4-5 marks)\":\"Correctly explains dual-mode operation and the vulnerability, but lacks depth in hardware-level enforcement details.\",\"Developing (2-3 marks)\":\"Basic understanding of user vs. kernel mode, but fails to clearly link it to the security flaw.\",\"Unsatisfactory (0-1 marks)\":\"Incorrect or incomplete explanation of dual-mode operations and privilege boundaries.\"}},{\"criterion\":\"System Call Flow and Trap Vector Tracing\",\"description\":\"Illustrate and detail the step-by-step mechanism of system call invocation, software interrupt\\/trap generation, context saving, and return-from-trap execution.\",\"marks\":7,\"clo\":\"CLO2\",\"bloom\":\"K3\",\"levels\":{\"Excellent (6-7 marks)\":\"Comprehensive and accurately sequenced diagram\\/trace of system call dispatching, trap vectors, register preservation, and return to user mode.\",\"Good (4-5 marks)\":\"Accurate trace of the system call mechanism with minor omissions in register handling or trap vector specifics.\",\"Developing (2-3 marks)\":\"Incomplete execution sequence with significant steps missing in the user-to-kernel transition.\",\"Unsatisfactory (0-1 marks)\":\"Fails to trace system call execution or provides fundamentally incorrect flow.\"}},{\"criterion\":\"Architectural Evaluation and Mitigation Strategy\",\"description\":\"Critically evaluate the system structure (Monolithic vs. Microkernel vs. Layered\\/Modular) and propose architectural safeguards to prevent similar system failures.\",\"marks\":6,\"clo\":\"CLO2\",\"bloom\":\"K4\",\"levels\":{\"Excellent (5-6 marks)\":\"Insightful comparative evaluation of OS architectures with well-justified mitigation strategies and performance\\/security trade-off analysis.\",\"Good (3-4 marks)\":\"Sound evaluation of OS architectures with viable mitigation recommendations, though trade-offs are briefly discussed.\",\"Developing (1-2 marks)\":\"Superficial comparison of architectures with generic mitigation strategies.\",\"Unsatisfactory (0 marks)\":\"Fails to provide architectural evaluation or actionable mitigations.\"}}]}', 'gemini-3.7-flash', NULL, NULL, 12535, 'success', NULL, 'assignment', 2, '2026-09-30 05:20:08');

-- --------------------------------------------------------

--
-- Table structure for table `ai_prompt_templates`
--

CREATE TABLE `ai_prompt_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(64) NOT NULL,
  `module` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `system_prompt` longtext NOT NULL,
  `user_template` longtext DEFAULT NULL,
  `output_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_schema`)),
  `model` varchar(80) DEFAULT 'gemini-2.0-flash',
  `version` int(10) UNSIGNED DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_prompt_templates`
--

INSERT INTO `ai_prompt_templates` (`id`, `code`, `module`, `name`, `system_prompt`, `user_template`, `output_schema`, `model`, `version`, `is_active`, `meta`, `updated_at`) VALUES
(1, 'course_plan', 'course_plan', 'Course Plan Generator', 'You are an expert Indian higher-education curriculum designer specializing in OBE, Bloom\'s taxonomy (K1-K6), NAAC Binary 2025 and NBA GAPC v4. Return ONLY valid JSON.', 'Subject: {{subject}}\nCredits: {{credits}}\nUniversity: {{university}}\nSyllabus:\n{{syllabus}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(2, 'bloom_map', 'bloom', 'Bloom Mapper', 'Map each unit/topic to Bloom K1-K6. Return ONLY valid JSON with units array and distribution percentages.', '{{plan_json}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(3, 'ai_review', 'review', 'Curriculum Review', 'Evaluate the course plan on 12 parameters (NAAC, industry, OBE, resources, hours, K-balance, etc). Return JSON with score 0-100 and recommendations.', '{{plan_json}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(4, 'lesson_plan', 'lesson', 'Lesson Planner', 'Generate session-by-session lesson plans from the course plan. Return JSON array of sessions.', '{{plan_json}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(5, 'question_bank', 'questions', 'Question Bank', 'Generate exam questions (MCQ, short, long) tagged by Bloom K-level and unit. Return ONLY JSON.', 'Type: {{type}}\nUnit: {{unit}}\nK-level: {{klevel}}\nCount: {{count}}\nSyllabus context:\n{{context}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(6, 'ppt_gen', 'ppt', 'PPT Generator', 'Generate a professional 12-20 slide teaching presentation as JSON slides with title, bullets, speaker_notes, unit_tag.', '{{context}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(7, 'assignment_gen', 'assignment', 'Assignment Generator', 'Generate a NAAC-compliant assignment with rubric for the given type. Return ONLY JSON.', 'Type: {{type}}\nSubject: {{subject}}\nContext:\n{{context}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(8, 'formula_nlp', 'marks', 'Formula NLP Parser', 'Parse plain-English internal marks formula used in Indian universities into structured JSON components and expression.', '{{formula_text}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(9, 'study_assistant', 'ask_ai', 'Student Study Assistant', 'Answer using ONLY the provided course materials. Cite sources. If unknown, say so.', 'Materials:\n{{materials}}\n\nQuestion: {{question}}', NULL, 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02'),
(10, 'improve_plan', 'improve', 'Improve with AI', 'Apply the professor instruction to improve the course plan. Return full updated plan JSON and a change summary.', 'Instruction: {{instruction}}\nCurrent plan:\n{{plan_json}}', '{\"type\": \"object\"}', 'gemini-2.0-flash', 1, 1, NULL, '2026-08-08 17:07:02');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `announcement_type` enum('general','exam','event','holiday','circular','deadline') DEFAULT 'general',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED DEFAULT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`setting_value`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED DEFAULT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `assignment_type` enum('essay','case_study','research_review','problem_solving','mini_project','mixed','lab','reflection','group_presentation') NOT NULL DEFAULT 'essay',
  `description` longtext DEFAULT NULL,
  `rubric` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rubric`)),
  `max_marks` decimal(6,2) DEFAULT 25.00,
  `deadline` datetime DEFAULT NULL,
  `instructions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`instructions`)),
  `ai_generated` tinyint(1) DEFAULT 0,
  `status` enum('draft','published','closed') DEFAULT 'draft',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `institution_id`, `plan_id`, `professor_id`, `subject_id`, `class_id`, `title`, `assignment_type`, `description`, `rubric`, `max_marks`, `deadline`, `instructions`, `ai_generated`, `status`, `meta`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 3, 1, 1, 'Case Study Assignment: E-Commerce Database Optimization and Normalization', 'case_study', 'Students are required to analyze a flawed relational database schema for a rapidly growing e-commerce platform, identify anomalies, redesign the schema up to Boyce-Codd Normal Form (BCNF), write complex SQL queries for business analytics, and propose indexing strategies for performance optimization.', '[{\"criterion\":\"Functional Dependencies and Anomaly Analysis\",\"description\":\"Accurately identifies functional dependencies and clearly explains update, insertion, and deletion anomalies in the given schema.\",\"marks\":10,\"clo\":\"CLO1\",\"bloom\":\"K2\",\"levels\":\"Array\",\"weight\":null},{\"criterion\":\"Schema Normalization (Up to BCNF)\",\"description\":\"Successfully decomposes relations to eliminate redundancy and achieves Boyce-Codd Normal Form (BCNF) while preserving dependencies.\",\"marks\":15,\"clo\":\"CLO2\",\"bloom\":\"K3\",\"levels\":\"Array\",\"weight\":null},{\"criterion\":\"Advanced SQL Query Formulation\",\"description\":\"Writes efficient and accurate SQL queries involving multi-table joins, subqueries, group by clauses, and window functions.\",\"marks\":15,\"clo\":\"CLO3\",\"bloom\":\"K3\",\"levels\":\"Array\",\"weight\":null},{\"criterion\":\"Indexing and Performance Optimization\",\"description\":\"Proposes appropriate indexing strategies (B-Trees, composite indexes) and interprets query execution plans to resolve performance issues.\",\"marks\":10,\"clo\":\"CLO4\",\"bloom\":\"K3\",\"levels\":\"Array\",\"weight\":null}]', 50.00, '2026-09-20 09:00:00', '[\"Read the provided case study scenario detailing the \'ShopSmart\' e-commerce database schema and its current performance bottlenecks.\",\"Part A: Identify and document functional dependencies and anomalies (insertion, update, deletion) present in the unnormalized tables.\",\"Part B: Normalize the database schema progressively from 1NF up to BCNF, showing all intermediate steps.\",\"Part C: Write optimized SQL queries to generate specific monthly sales reports, customer segmentation insights, and inventory alerts.\",\"Part D: Propose suitable indexes and query execution plan improvements to resolve reported slow-loading dashboard issues.\",\"Submit a comprehensive PDF report containing the schema diagrams, SQL script outputs, and analytical explanations.\"]', 1, 'published', '{\"bulk_group\":null,\"from_template_id\":null,\"context\":\"generate the assignment for c\"}', '2026-09-19 08:04:56', '2026-09-19 08:04:56'),
(2, 1, NULL, 5, 2, 1, 'Case Study: Architectural Analysis and Kernel-Mode Transition Failures in Enterprise Operating Systems', 'case_study', 'Analyze a real-world scenario involving a mission-critical server experiencing performance degradation and unauthorized privilege escalation due to flawed system call handling and improper hardware-level dual-mode enforcement. Students will investigate the core operating system architecture, distinguish between user and kernel mode operations, trace interrupt/trap execution paths, and evaluate the trade-offs between monolithic and microkernel architectures.', '[{\"criterion\":\"Identification of OS Components and Core Services\",\"description\":\"Accurately identify and explain the role of fundamental OS components (process management, memory management, storage abstraction, and protection subsystems) relevant to the case.\",\"marks\":5,\"clo\":\"CLO1\",\"bloom\":\"K2\",\"levels\":\"Array\",\"weight\":null},{\"criterion\":\"Dual-Mode Execution & Privilege Boundary Analysis\",\"description\":\"Analyze the mechanics of dual-mode operation (User Mode vs. Kernel Mode), hardware mode bit manipulation, and the root cause of the privilege escalation vulnerability.\",\"marks\":7,\"clo\":\"CLO1\",\"bloom\":\"K3\",\"levels\":\"Array\",\"weight\":null},{\"criterion\":\"System Call Flow and Trap Vector Tracing\",\"description\":\"Illustrate and detail the step-by-step mechanism of system call invocation, software interrupt\\/trap generation, context saving, and return-from-trap execution.\",\"marks\":7,\"clo\":\"CLO2\",\"bloom\":\"K3\",\"levels\":\"Array\",\"weight\":null},{\"criterion\":\"Architectural Evaluation and Mitigation Strategy\",\"description\":\"Critically evaluate the system structure (Monolithic vs. Microkernel vs. Layered\\/Modular) and propose architectural safeguards to prevent similar system failures.\",\"marks\":6,\"clo\":\"CLO2\",\"bloom\":\"K4\",\"levels\":\"Array\",\"weight\":null}]', 25.00, '2026-10-01 09:00:00', '[\"Read the provided case study scenario detailing the dual-mode transition failure and system call bottlenecks in an enterprise server environment.\",\"Identify the foundational operating system components involved in hardware abstraction, resource management, and system call dispatching.\",\"Diagram and trace the complete lifecycle of a system call from user-space execution, mode-bit transition, trap handling, to kernel-space service routine execution.\",\"Evaluate the security implications of user\\/kernel mode boundary breaches and propose remediation using appropriate OS architectural paradigms.\",\"Submit your detailed analytical report in standard format (PDF) within the designated deadline adhering to academic integrity standards.\"]', 1, 'published', '{\"bulk_group\":null,\"from_template_id\":null,\"context\":\"Build the Assignment of First chapter\"}', '2026-09-30 05:20:08', '2026-09-30 05:20:08');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_extension_requests`
--

CREATE TABLE `assignment_extension_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `requested_deadline` datetime NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `professor_note` text DEFAULT NULL,
  `decided_by` int(10) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `approved_deadline` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `content_text` longtext DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `grade` decimal(6,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(10) UNSIGNED DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `status` enum('draft','submitted','late','graded','returned') DEFAULT 'draft',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assignment_submissions`
--

INSERT INTO `assignment_submissions` (`id`, `assignment_id`, `student_id`, `content_text`, `file_url`, `submitted_at`, `grade`, `feedback`, `graded_by`, `graded_at`, `status`, `meta`) VALUES
(1, 1, 4, 'Part 1: Flawed Schema Analysis & Anomaly IdentificationFlawed Schema: Unnormalized_OrdersConsider an unnormalized table tracking orders, items, customers, and shipping:Unnormalized_Orders (\r\n    order_id, \r\n    customer_id, \r\n    customer_name, \r\n    customer_email, \r\n    customer_city, \r\n    product_id, \r\n    product_name, \r\n    category_id, \r\n    category_name, \r\n    supplier_id, \r\n    supplier_city, \r\n    quantity, \r\n    unit_price, \r\n    order_date, \r\n    shipping_status\r\n)\r\nAnomaly AnalysisInsertion Anomaly:You cannot add a new product or supplier without creating a dummy order (because order_id and product_id form part of the composite key).A new customer cannot exist in the system until they place their first order.Deletion Anomaly:If customer C100 cancels their only order (O500), all historical details about C100 (name, email, city) are permanently lost from the database.Update / Modification Anomaly:If supplier S20 updates their supplier_city, every row containing a product supplied by S20 across all historical orders must be updated. Missing even one row leads to data inconsistency.Part 2: Normalization to BCNF (Step-by-Step)Functional Dependencies (FDs)From business logic, we identify the following FDs:$FD_1$: $\\text{order\\_id}, \\text{product\\_id} \\rightarrow \\text{quantity}, \\text{unit\\_price}, \\text{order\\_date}, \\text{shipping\\_status}$$FD_2$: $\\text{order\\_id} \\rightarrow \\text{customer\\_id}, \\text{order\\_date}, \\text{shipping\\_status}$$FD_3$: $\\text{customer\\_id} \\rightarrow \\text{customer\\_name}, \\text{customer\\_email}, \\text{customer\\_city}$$FD_4$: $\\text{product\\_id} \\rightarrow \\text{product\\_name}, \\text{category\\_id}, \\text{supplier\\_id}$$FD_5$: $\\text{category\\_id} \\rightarrow \\text{category\\_name}$$FD_6$: $\\text{supplier\\_id} \\rightarrow \\text{supplier\\_city}$$FD_7$: $\\text{customer\\_email} \\rightarrow \\text{customer\\_id}$ (Candidate key for customer)Step 1: First Normal Form (1NF)Rule: Eliminate repeating groups and ensure atomic values.Action: Define composite Primary Key: $(\\text{order\\_id}, \\text{product\\_id})$.Step 2: Second Normal Form (2NF)Rule: Remove partial dependencies (attributes depending on only part of the composite primary key).Violations: $FD_2, FD_3, FD_4, FD_5, FD_6$ depend on either order_id or product_id alone, not the combination $(\\text{order\\_id}, \\text{product\\_id})$.Decomposition into 2NF:Order_Items: ($\\underline{\\text{order\\_id}, \\text{product\\_id}}$, quantity, unit_price)Orders: ($\\underline{\\text{order\\_id}}$, customer_id, order_date, shipping_status)Customers: ($\\underline{\\text{customer\\_id}}$, customer_name, customer_email, customer_city)Products: ($\\underline{\\text{product\\_id}}$, product_name, category_id, category_name, supplier_id, supplier_city)Step 3: Third Normal Form (3NF)Rule: Remove transitive dependencies ($X \\rightarrow Y$ and $Y \\rightarrow Z$).Violations in Products: $\\text{product\\_id} \\rightarrow \\text{category\\_id} \\rightarrow \\text{category\\_name}$ and $\\text{product\\_id} \\rightarrow \\text{supplier\\_id} \\rightarrow \\text{supplier\\_city}$.Decomposition into 3NF:Order_Items: ($\\underline{\\text{order\\_id}, \\text{product\\_id}}$, quantity, unit_price)Orders: ($\\underline{\\text{order\\_id}}$, customer_id, order_date, shipping_status)Customers: ($\\underline{\\text{customer\\_id}}$, customer_name, customer_email, customer_city)Products: ($\\underline{\\text{product\\_id}}$, product_name, category_id, supplier_id)Categories: ($\\underline{\\text{category\\_id}}$, category_name)Suppliers: ($\\underline{\\text{supplier\\_id}}$, supplier_city)Step 4: Boyce-Codd Normal Form (BCNF)Rule: For every non-trivial functional dependency $X \\rightarrow Y$, $X$ must be a superkey.Check Customers Table:$FD_3$: $\\text{customer\\_id} \\rightarrow \\text{customer\\_name}, \\text{customer\\_email}, \\text{customer\\_city}$ (customer_id is a key $\\checkmark$)$FD_7$: $\\text{customer\\_email} \\rightarrow \\text{customer\\_id}$ (customer_email is a unique candidate key $\\checkmark$)Since every determinant in all relations is a superkey, the decomposed schema is now in BCNF.Final BCNF Relational Schema (DDL)SQLCREATE TABLE Customers (\r\n    customer_id INT PRIMARY KEY AUTO_INCREMENT,\r\n    customer_name VARCHAR(100) NOT NULL,\r\n    customer_email VARCHAR(150) UNIQUE NOT NULL,\r\n    customer_city VARCHAR(100) NOT NULL\r\n);\r\n\r\nCREATE TABLE Categories (\r\n    category_id INT PRIMARY KEY AUTO_INCREMENT,\r\n    category_name VARCHAR(100) NOT NULL\r\n);\r\n\r\nCREATE TABLE Suppliers (\r\n    supplier_id INT PRIMARY KEY AUTO_INCREMENT,\r\n    supplier_city VARCHAR(100) NOT NULL\r\n);\r\n\r\nCREATE TABLE Products (\r\n    product_id INT PRIMARY KEY AUTO_INCREMENT,\r\n    product_name VARCHAR(150) NOT NULL,\r\n    category_id INT NOT NULL,\r\n    supplier_id INT NOT NULL,\r\n    FOREIGN KEY (category_id) REFERENCES Categories(category_id),\r\n    FOREIGN KEY (supplier_id) REFERENCES Suppliers(supplier_id)\r\n);\r\n\r\nCREATE TABLE Orders (\r\n    order_id INT PRIMARY KEY AUTO_INCREMENT,\r\n    customer_id INT NOT NULL,\r\n    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\r\n    shipping_status VARCHAR(50) NOT NULL,\r\n    FOREIGN KEY (customer_id) REFERENCES Customers(customer_id)\r\n);\r\n\r\nCREATE TABLE Order_Items (\r\n    order_id INT NOT NULL,\r\n    product_id INT NOT NULL,\r\n    quantity INT NOT NULL CHECK (quantity > 0),\r\n    unit_price DECIMAL(10, 2) NOT NULL,\r\n    PRIMARY KEY (order_id, product_id),\r\n    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,\r\n    FOREIGN KEY (product_id) REFERENCES Products(product_id)\r\n);\r\nPart 3: Complex Analytics SQL QueriesQuery 1: Customer Lifetime Value (CLV) & Ranking with Window FunctionsFind top customers by total expenditure, ranking them per city using DENSE_RANK().SQLWITH CustomerRevenue AS (\r\n    SELECT \r\n        c.customer_id,\r\n        c.customer_name,\r\n        c.customer_city,\r\n        SUM(oi.quantity * oi.unit_price) AS total_spent,\r\n        COUNT(DISTINCT o.order_id) AS total_orders\r\n    FROM Customers c\r\n    JOIN Orders o ON c.customer_id = o.customer_id\r\n    JOIN Order_Items oi ON o.order_id = oi.order_id\r\n    GROUP BY c.customer_id, c.customer_name, c.customer_city\r\n)\r\nSELECT \r\n    customer_id,\r\n    customer_name,\r\n    customer_city,\r\n    total_spent,\r\n    total_orders,\r\n    DENSE_RANK() OVER (PARTITION BY customer_city ORDER BY total_spent DESC) AS city_rank\r\nFROM CustomerRevenue\r\nORDER BY customer_city, city_rank;\r\nQuery 2: Month-over-Month (MoM) Growth in SalesCalculate monthly revenue and percentage growth compared to the previous month using LAG().SQLWITH MonthlySales AS (\r\n    SELECT \r\n        DATE_FORMAT(o.order_date, \'%Y-%m\') AS sales_month,\r\n        SUM(oi.quantity * oi.unit_price) AS current_month_revenue\r\n    FROM Orders o\r\n    JOIN Order_Items oi ON o.order_id = oi.order_id\r\n    GROUP BY DATE_FORMAT(o.order_date, \'%Y-%m\')\r\n)\r\nSELECT \r\n    sales_month,\r\n    current_month_revenue,\r\n    LAG(current_month_revenue, 1) OVER (ORDER BY sales_month) AS prev_month_revenue,\r\n    ROUND(\r\n        (current_month_revenue - LAG(current_month_revenue, 1) OVER (ORDER BY sales_month)) \r\n        / LAG(current_month_revenue, 1) OVER (ORDER BY sales_month) * 100, 2\r\n    ) AS mom_growth_percentage\r\nFROM MonthlySales;\r\nPart 4: Indexing & Performance OptimizationFor an e-commerce platform processing millions of read/write transactions daily:1. Composite Index for Range Queries & JoinsTarget Query: Searching orders by customer within date ranges (WHERE customer_id = X AND order_date >= Y).Strategy: Create a Composite B-Tree index ordering high-cardinality equality columns first, followed by range filter columns.SQLCREATE INDEX idx_orders_customer_date \r\nON Orders(customer_id, order_date);\r\n2. Covering Index for Analytic QueriesTarget Query: Aggregating sales metrics on Order_Items.Strategy: Include (order_id, product_id, quantity, unit_price) in an index to satisfy queries directly from index leaf pages (Index-Only Scan) without fetching data blocks from disk.SQLCREATE INDEX idx_order_items_covering \r\nON Order_Items(order_id, product_id, quantity, unit_price);\r\n3. Partial / Filtered Index (For High Skew Data)Target Query: Tracking active/pending deliveries.Strategy: Index only processing or pending orders, ignoring millions of completed orders to reduce index maintenance overhead.SQL-- PostgreSQL / SQL Server Syntax\r\nCREATE INDEX idx_pending_orders \r\nON Orders(order_date) \r\nWHERE shipping_status = \'PENDING\';\r\nSummary MatrixMetric / GoalUnnormalized TableBCNF SchemaData RedundancyExtremely HighZero (Minimal foreign keys)AnomaliesInsert, Update, Delete presentFully EliminatedQuery PerformanceFast simple SELECTs, slow updatesRequires JOINs, highly optimized with indexesData IntegrityHighly vulnerable to corruptionGuaranteed by Relational Constraints', NULL, '2026-09-30 09:53:18', 29.00, 'The student submission demonstrates a strong understanding of database normalization, functional dependency analysis, and anomaly identification. The step-by-step breakdown to BCNF along with full DDL script is accurate and well-articulated. However, the submission is incomplete: the SQL query section cuts off midway, and Part 4 (Indexing and Performance Optimization) is completely omitted.', 3, '2026-09-30 09:54:57', 'graded', '{\"ai_grade\":{\"score\":29,\"feedback\":\"The student submission demonstrates a strong understanding of database normalization, functional dependency analysis, and anomaly identification. The step-by-step breakdown to BCNF along with full DDL script is accurate and well-articulated. However, the submission is incomplete: the SQL query section cuts off midway, and Part 4 (Indexing and Performance Optimization) is completely omitted.\",\"criterion_scores\":[{\"criterion\":\"Functional Dependencies and Anomaly Analysis\",\"score\":10,\"comment\":\"Excellently identified and explained update, insertion, and deletion anomalies, along with clear specification of functional dependencies.\"},{\"criterion\":\"Schema Normalization (Up to BCNF)\",\"score\":15,\"comment\":\"Flawlessly decomposed the schema step-by-step from 1NF to BCNF while maintaining functional dependencies, and provided accurate SQL DDL statements.\"},{\"criterion\":\"Advanced SQL Query Formulation\",\"score\":4,\"comment\":\"The SQL query section is cut off mid-statement in Query 1 and incomplete. Minimal partial marks awarded for starting the CTE structure.\"},{\"criterion\":\"Indexing and Performance Optimization\",\"score\":0,\"comment\":\"This section was entirely missing from the submission.\"}],\"at\":\"2026-09-30T09:54:42+05:30\",\"by\":3,\"provisional\":true},\"final_grade\":{\"score\":29,\"feedback\":\"The student submission demonstrates a strong understanding of database normalization, functional dependency analysis, and anomaly identification. The step-by-step breakdown to BCNF along with full DDL script is accurate and well-articulated. However, the submission is incomplete: the SQL query section cuts off midway, and Part 4 (Indexing and Performance Optimization) is completely omitted.\",\"at\":\"2026-09-30T09:54:57+05:30\",\"by\":3,\"override\":false,\"ai_score\":29},\"finalized\":true}');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_templates`
--

CREATE TABLE `assignment_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `assignment_type` varchar(40) NOT NULL DEFAULT 'essay',
  `description` longtext DEFAULT NULL,
  `rubric` longtext DEFAULT NULL,
  `max_marks` decimal(6,2) NOT NULL DEFAULT 25.00,
  `instructions` longtext DEFAULT NULL,
  `context_text` longtext DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_qr_tokens`
--

CREATE TABLE `attendance_qr_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED DEFAULT NULL,
  `session_date` date NOT NULL,
  `period` varchar(20) NOT NULL DEFAULT '1',
  `topic` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `geofence_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `register_no` varchar(60) NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `session_id`, `student_id`, `register_no`, `status`, `meta`) VALUES
(1, 1, 4, '224026', 'present', NULL),
(2, 2, 4, '224026', 'absent', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_regularization_requests`
--

CREATE TABLE `attendance_regularization_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `register_no` varchar(40) NOT NULL,
  `original_status` varchar(20) NOT NULL,
  `requested_status` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `proof_url` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `professor_note` text DEFAULT NULL,
  `decided_by` int(10) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sessions`
--

CREATE TABLE `attendance_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `session_date` date NOT NULL,
  `period` varchar(40) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `records` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '[{student_id/reg, status}]' CHECK (json_valid(`records`)),
  `present_count` int(10) UNSIGNED DEFAULT 0,
  `absent_count` int(10) UNSIGNED DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_sessions`
--

INSERT INTO `attendance_sessions` (`id`, `institution_id`, `professor_id`, `subject_id`, `class_id`, `session_date`, `period`, `topic`, `records`, `present_count`, `absent_count`, `meta`, `created_at`) VALUES
(1, 1, 3, 1, 1, '2026-09-30', '1', 'DBMS Introduction', '[{\"register_no\":224026,\"status\":\"present\"}]', 1, 0, NULL, '2026-09-30 04:17:51'),
(2, 1, 5, 2, 1, '2026-09-30', '1', 'Operating System Introduction', '[{\"register_no\":224026,\"status\":\"absent\"}]', 0, 1, NULL, '2026-09-30 04:18:46');

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `fiscal_year` varchar(20) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `allocated` decimal(12,2) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `year` tinyint(3) UNSIGNED DEFAULT NULL,
  `semester` varchar(40) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `institution_id`, `department_id`, `program_id`, `name`, `section`, `year`, `semester`, `academic_year`, `meta`, `is_active`) VALUES
(1, 1, 1, NULL, 'CSE', 'A', 1, NULL, '2026-27', '{\"level\":\"UG\"}', 1);

-- --------------------------------------------------------

--
-- Table structure for table `compliance_alerts`
--

CREATE TABLE `compliance_alerts` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `plan_id` int(10) UNSIGNED DEFAULT NULL,
  `alert_type` varchar(60) NOT NULL,
  `severity` enum('low','medium','high') DEFAULT 'medium',
  `message` text NOT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_plans`
--

CREATE TABLE `course_plans` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `subject_name` varchar(200) NOT NULL,
  `subject_code` varchar(40) DEFAULT NULL,
  `credits` decimal(4,1) DEFAULT 3.0,
  `semester` varchar(40) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `university` varchar(150) DEFAULT NULL,
  `syllabus_input` longtext DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','returned') DEFAULT 'draft',
  `ai_score` decimal(5,2) DEFAULT NULL,
  `ai_review` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_review`)),
  `bloom_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bloom_data`)),
  `weekly_plan` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weekly_plan`)),
  `resources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resources`)),
  `expert_advice` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`expert_advice`)),
  `plan_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Full structured plan payload' CHECK (json_valid(`plan_data`)),
  `version` int(10) UNSIGNED DEFAULT 1,
  `parent_plan_id` int(10) UNSIGNED DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `hod_comments` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `share_token` varchar(64) DEFAULT NULL COMMENT 'Public read-only share token',
  `share_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=public read-only link active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_plans`
--

INSERT INTO `course_plans` (`id`, `institution_id`, `department_id`, `professor_id`, `subject_id`, `class_id`, `title`, `subject_name`, `subject_code`, `credits`, `semester`, `academic_year`, `university`, `syllabus_input`, `status`, `ai_score`, `ai_review`, `bloom_data`, `weekly_plan`, `resources`, `expert_advice`, `plan_data`, `version`, `parent_plan_id`, `submitted_at`, `reviewed_at`, `reviewed_by`, `hod_comments`, `meta`, `share_token`, `share_enabled`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 3, 1, 1, 'Database Management Systems', 'Database Management Systems', NULL, 4.0, NULL, NULL, 'Autonomous', 'Unit 1: Introduction to DBMS & Database ArchitectureDatabase System Concepts: Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS, Data Abstraction, Data Independence (Physical and Logical).Database Architecture: Three-Schema Architecture (Internal, Conceptual, External), Database Languages (DDL, DML, DCL, TCL), Database Interfaces, Roles of Database Users and DBAs.Data Modeling: Entity-Relationship (ER) Model—Entities, Attributes, Entity Sets, Relationships, Mapping Cardinalities, Keys (Super Key, Candidate Key, Primary Key, Foreign Key).Extended ER (EER) Modeling: Specialization, Generalization, Aggregation, Reduction of ER/EER Diagrams to Relational Tables.Unit 2: Relational Model & Query LanguagesRelational Data Model: Structure of Relational Databases, Relational Model Constraints (Domain, Entity Integrity, Referential Integrity).Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference, Cartesian Product, Rename) and Additional Operations (Join, Division, Intersection).Structured Query Language (SQL):DDL Commands: CREATE, ALTER, DROP, TRUNCATE.DML Commands: INSERT, UPDATE, DELETE, SELECT.Integrity Constraints: NOT NULL, UNIQUE, PRIMARY KEY, FOREIGN KEY, CHECK, DEFAULT.Queries & Joins: Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries, Set Operations, Inner/Outer Joins.Views & Indexes: Creating Views, Updatable Views, Index Structures.Unit 3: Relational Database Design & NormalizationPitfalls in Relational Design: Data Redundancy, Insertion, Deletion, and Update Anomalies.Functional Dependencies: Definition, Trivial and Non-trivial Dependencies, Closure of Functional Dependencies, Armstrong’s Axioms, Canonical Cover.Normalization:First Normal Form (1NF): Atomicity of attributes.Second Normal Form (2NF): Eliminating partial dependencies.Third Normal Form (3NF): Eliminating transitive dependencies.Boyce-Codd Normal Form (BCNF): Strict candidate key constraints.Higher Normal Forms: Fourth Normal Form (4NF - Multivalued Dependencies) and Fifth Normal Form (5NF - Join Dependencies).Decomposition Properties: Lossless-Join Decomposition, Dependency-Preserving Decomposition.Unit 4: Transaction Management & Concurrency ControlTransaction Concepts: Definition, Transaction States, ACID Properties (Atomicity, Consistency, Isolation, Durability).Concurrency Control:Schedules: Concurrent Executions, Serial Schedules, Serializability (Conflict and View Serializability), Recoverability (Cascadeless and Recoverable Schedules).Lock-Based Protocols: Shared/Exclusive Locks, Two-Phase Locking (2PL - Strict 2PL, Rigorous 2PL).Deadlocks: Deadlock Prevention, Detection, and Recovery.Timestamp-Based & Validation-Based Protocols: Timestamp Ordering Protocol, Thomas\' Write Rule.Unit 5: Storage Structures, Indexing & RecoveryStorage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels.Indexing & Hashing: Primary Index, Secondary Index, Clustered Index, Sparse vs Dense Index; Dynamic Hashing, Tree-Structured Indexing ($B\\text{-Trees}$ and $B^+\\text{-Trees}$).Database Recovery Techniques:Failure Classification (System Crash, Transaction Failure, Disk Failure).Log-Based Recovery (Deferred Database Modification, Immediate Database Modification).Checkpoints and Shadow Paging.', 'submitted', 96.00, NULL, '{\"K1\":10,\"K2\":20,\"K3\":35,\"K4\":25,\"K5\":10,\"K6\":0}', '[{\"week\":1,\"topics\":[\"Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS\",\"Data Abstraction, Data Independence (Physical and Logical)\"],\"pedagogy\":\"Interactive Lecture & Comparative Discussion\"},{\"week\":2,\"topics\":[\"Three-Schema Architecture, Database Languages (DDL, DML, DCL, TCL), Database Interfaces\",\"Roles of Database Users and DBAs\"],\"pedagogy\":\"Chalk and Board, Role-play activity for DBA vs User roles\"},{\"week\":3,\"topics\":[\"ER Model: Entities, Attributes, Relationships, Keys\",\"EER Modeling: Generalization, Specialization, Aggregation\",\"Reduction of ER\\/EER Diagrams to Relational Tables\"],\"pedagogy\":\"Collaborative Group Design Session, Case Study Analysis\"},{\"week\":4,\"topics\":[\"Structure of Relational Databases, Relational Model Constraints\",\"Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference)\"],\"pedagogy\":\"Problem-Solving Session, Interactive Quizzes\"},{\"week\":5,\"topics\":[\"Relational Algebra: Cartesian Product, Rename, Join, Division, Intersection\",\"SQL DDL & DML Commands, Basic Queries\"],\"pedagogy\":\"Hands-on Lab Session, Live Coding Demonstrations\"},{\"week\":6,\"topics\":[\"Integrity Constraints, Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries\",\"Views & Indexes: Creation and Updatability\"],\"pedagogy\":\"Lab-based Query Optimization Exercises, Peer Code Reviews\"},{\"week\":7,\"topics\":[\"Pitfalls in Relational Design, Data Redundancy, Anomalies\",\"Functional Dependencies, Closure of FDs, Armstrong\'s Axioms\"],\"pedagogy\":\"Case-driven Lecture, Mathematical Proof Walkthroughs\"},{\"week\":8,\"topics\":[\"Canonical Cover, First Normal Form (1NF), Second Normal Form (2NF), Third Normal Form (3NF)\"],\"pedagogy\":\"Step-by-step Problem Solving, Flipped Classroom on Anomalies\"},{\"week\":9,\"topics\":[\"Boyce-Codd Normal Form (BCNF), Higher Normal Forms (4NF, 5NF)\",\"Decomposition Properties: Lossless-Join and Dependency Preservation\"],\"pedagogy\":\"Analytical Problem Solving, Peer Instruction\"},{\"week\":10,\"topics\":[\"Transaction Concepts, ACID Properties, Transaction States\",\"Schedules, Serializability (Conflict and View Serializability)\"],\"pedagogy\":\"Visual Timeline Tracing, Real-world Transaction Failure Case Studies\"},{\"week\":11,\"topics\":[\"Recoverability (Cascadeless and Recoverable Schedules)\",\"Lock-Based Protocols: Shared\\/Exclusive Locks, Two-Phase Locking (2PL, Strict 2PL, Rigorous 2PL)\"],\"pedagogy\":\"Interactive Simulation, Scenario-based Analysis\"},{\"week\":12,\"topics\":[\"Deadlocks: Prevention, Detection, and Recovery\",\"Timestamp-Based & Validation-Based Protocols, Thomas\' Write Rule\"],\"pedagogy\":\"Algorithmic Dry-runs, Group Discussion on Deadlock Trade-offs\"},{\"week\":13,\"topics\":[\"Storage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels\",\"Indexing: Primary, Secondary, Clustered, Sparse vs Dense Index\"],\"pedagogy\":\"Comparative Analysis, Architectural Diagrams\"},{\"week\":14,\"topics\":[\"Dynamic Hashing, Tree-Structured Indexing (B-Trees and B+-Trees)\"],\"pedagogy\":\"Animation-based Tutorials, Step-by-step Tree Modification Exercises\"},{\"week\":15,\"topics\":[\"Database Recovery Techniques: Failure Classification, Log-Based Recovery (Deferred & Immediate Modification)\",\"Checkpoints and Shadow Paging\"],\"pedagogy\":\"Trace-based Problem Solving, Course Review, and Mock Exam\"}]', '[{\"type\":\"Textbook\",\"details\":\"Silberschatz, A., Korth, H. F., & Sudarshan, S. (2020). Database System Concepts (7th ed.). McGraw-Hill.\"},{\"type\":\"Reference Book\",\"details\":\"Elmasri, R., & Navathe, S. B. (2017). Fundamentals of Database Systems (7th ed.). Pearson.\"},{\"type\":\"Online Course\",\"details\":\"NPTEL: \'Database Management Systems\' by Prof. Partha Pratim Das, IIT Kharagpur.\"},{\"type\":\"Tool\",\"details\":\"PostgreSQL Open-Source Relational Database Management System.\"}]', '[\"Ensure students write SQL queries by hand before executing them in the lab. This builds strong syntax retention and logical query formulation.\",\"Emphasize the mathematical foundations of relational algebra as it directly translates to query optimization in real-world database engines.\",\"When teaching normalization, use realistic, messy business spreadsheets as starting points rather than clean, pre-simplified textbook relations.\",\"Integrate a continuous mini-project where students design, normalize, implement, and query a database for a domain of their choice throughout the semester.\"]', '{\"title\":\"Database Management Systems\",\"learning_outcomes\":[\"CO1: Analyze database requirements and design conceptual schemas using Entity-Relationship (ER) and Extended ER (EER) modeling techniques.\",\"CO2: Formulate relational algebra expressions and write optimized Structured Query Language (SQL) queries to perform complex data manipulation and retrieval.\",\"CO3: Apply normalization theory to design logical relational database schemas, eliminating redundancies and anomalies up to Fifth Normal Form (5NF).\",\"CO4: Evaluate transaction schedules for serializability and design concurrency control mechanisms using lock-based and timestamp-based protocols.\",\"CO5: Analyze physical storage structures, implement efficient indexing mechanisms using B\\/B+ Trees, and design database recovery strategies.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to DBMS & Database Architecture\",\"hours\":12,\"topics\":[\"Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS\",\"Data Abstraction, Data Independence (Physical and Logical)\",\"Three-Schema Architecture (Internal, Conceptual, External)\",\"Database Languages (DDL, DML, DCL, TCL), Database Interfaces\",\"Roles of Database Users and DBAs\",\"ER Model: Entities, Attributes, Entity Sets, Relationships, Mapping Cardinalities, Keys (Super, Candidate, Primary, Foreign)\",\"Extended ER (EER) Modeling: Specialization, Generalization, Aggregation\",\"Reduction of ER\\/EER Diagrams to Relational Tables\"],\"outcomes\":[\"Differentiate between traditional file systems and modern DBMS architectures.\",\"Design conceptual schemas using ER and EER diagrams for real-world enterprise scenarios.\",\"Map complex ER\\/EER diagrams into structurally sound relational database tables.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Chalk and Board for architectural diagrams\",\"Collaborative group activity for ER modeling of real-world case studies\",\"Flipped classroom on DBMS vs File Systems\"],\"assessment\":[\"Class Test on ER-to-Relational mapping rules\",\"Evaluation of ER diagram design assignment for a given case study\"]},{\"unit_number\":2,\"title\":\"Relational Model & Query Languages\",\"hours\":12,\"topics\":[\"Structure of Relational Databases, Relational Model Constraints (Domain, Entity Integrity, Referential Integrity)\",\"Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference, Cartesian Product, Rename)\",\"Relational Algebra: Additional Operations (Join, Division, Intersection)\",\"SQL DDL Commands (CREATE, ALTER, DROP, TRUNCATE) and DML Commands (INSERT, UPDATE, DELETE, SELECT)\",\"Integrity Constraints: NOT NULL, UNIQUE, PRIMARY KEY, FOREIGN KEY, CHECK, DEFAULT\",\"Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries, Set Operations, Inner\\/Outer Joins\",\"Views & Indexes: Creating Views, Updatable Views, Index Structures\"],\"outcomes\":[\"Formulate formal relational algebra expressions for complex data retrieval queries.\",\"Construct robust SQL queries utilizing joins, nested subqueries, and aggregation to solve business logic requirements.\",\"Implement and enforce domain, entity, and referential integrity constraints in SQL.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Hands-on laboratory sessions using PostgreSQL\\/MySQL\",\"Problem-solving sessions for Relational Algebra\",\"Interactive live-coding demonstrations\"],\"assessment\":[\"Laboratory practical exam on SQL queries and Joins\",\"Online coding challenge on nested subqueries and constraints\"]},{\"unit_number\":3,\"title\":\"Relational Database Design & Normalization\",\"hours\":12,\"topics\":[\"Pitfalls in Relational Design: Data Redundancy, Insertion, Deletion, and Update Anomalies\",\"Functional Dependencies: Definition, Trivial and Non-trivial Dependencies, Closure of Functional Dependencies, Armstrong\\u2019s Axioms, Canonical Cover\",\"First Normal Form (1NF), Second Normal Form (2NF), Third Normal Form (3NF)\",\"Boyce-Codd Normal Form (BCNF)\",\"Higher Normal Forms: Fourth Normal Form (4NF - Multivalued Dependencies), Fifth Normal Form (5NF - Join Dependencies)\",\"Decomposition Properties: Lossless-Join Decomposition, Dependency-Preserving Decomposition\"],\"outcomes\":[\"Identify and analyze design anomalies in poorly structured relational schemas.\",\"Compute functional dependency closures, canonical covers, and candidate keys for relational schemas.\",\"Decompose relational schemas up to BCNF\\/5NF ensuring lossless-join and dependency-preservation properties.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Problem-driven learning using step-by-step normalization exercises\",\"Peer instruction on decomposition properties\",\"Guided design reviews of student-created schemas\"],\"assessment\":[\"Mid-Semester Examination containing analytical normalization problems\",\"Home assignment on finding Canonical Cover and testing Lossless-Join property\"]},{\"unit_number\":4,\"title\":\"Transaction Management & Concurrency Control\",\"hours\":12,\"topics\":[\"Transaction Concepts: Definition, Transaction States, ACID Properties\",\"Schedules: Concurrent Executions, Serial Schedules, Serializability (Conflict and View Serializability)\",\"Recoverability: Cascadeless and Recoverable Schedules\",\"Lock-Based Protocols: Shared\\/Exclusive Locks, Two-Phase Locking (2PL - Strict 2PL, Rigorous 2PL)\",\"Deadlocks: Deadlock Prevention, Detection, and Recovery\",\"Timestamp-Based & Validation-Based Protocols, Thomas\' Write Rule\"],\"outcomes\":[\"Analyze concurrent transaction schedules for conflict and view serializability.\",\"Apply lock-based and timestamp-based protocols to guarantee database consistency and isolation.\",\"Formulate strategies to prevent, detect, and resolve deadlocks in concurrent transaction environments.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Visual tracing of concurrent execution timelines\",\"Case-study analysis of financial transaction failures\",\"Interactive simulation tools for Lock-based protocols\"],\"assessment\":[\"Quizzes on Serializability and 2PL execution paths\",\"Analytical problem-solving test on Deadlock detection algorithms\"]},{\"unit_number\":5,\"title\":\"Storage Structures, Indexing & Recovery\",\"hours\":12,\"topics\":[\"Storage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels\",\"Indexing & Hashing: Primary, Secondary, Clustered Index, Sparse vs Dense Index\",\"Dynamic Hashing, Tree-Structured Indexing (B-Trees and B+-Trees)\",\"Database Recovery Techniques: Failure Classification (System Crash, Transaction Failure, Disk Failure)\",\"Log-Based Recovery (Deferred Database Modification, Immediate Database Modification)\",\"Checkpoints and Shadow Paging\"],\"outcomes\":[\"Compare different physical file organizations and RAID levels for optimal database performance.\",\"Construct and manipulate B-Trees and B+-Trees for indexing database attributes.\",\"Evaluate recovery algorithms (Deferred vs Immediate modifications) to restore database consistency after failures.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Animation-based demonstration of B+ Tree insertions and deletions\",\"Comparative analysis of RAID levels using real-world performance metrics\",\"Step-by-step walkthrough of log-based recovery scenarios\"],\"assessment\":[\"Assignment on B+ Tree dry-runs (insertion\\/deletion steps)\",\"End-Semester Examination questions on Log-based recovery and Checkpointing\"]}],\"weekly_plan\":[{\"week\":1,\"topics\":[\"Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS\",\"Data Abstraction, Data Independence (Physical and Logical)\"],\"pedagogy\":\"Interactive Lecture & Comparative Discussion\"},{\"week\":2,\"topics\":[\"Three-Schema Architecture, Database Languages (DDL, DML, DCL, TCL), Database Interfaces\",\"Roles of Database Users and DBAs\"],\"pedagogy\":\"Chalk and Board, Role-play activity for DBA vs User roles\"},{\"week\":3,\"topics\":[\"ER Model: Entities, Attributes, Relationships, Keys\",\"EER Modeling: Generalization, Specialization, Aggregation\",\"Reduction of ER\\/EER Diagrams to Relational Tables\"],\"pedagogy\":\"Collaborative Group Design Session, Case Study Analysis\"},{\"week\":4,\"topics\":[\"Structure of Relational Databases, Relational Model Constraints\",\"Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference)\"],\"pedagogy\":\"Problem-Solving Session, Interactive Quizzes\"},{\"week\":5,\"topics\":[\"Relational Algebra: Cartesian Product, Rename, Join, Division, Intersection\",\"SQL DDL & DML Commands, Basic Queries\"],\"pedagogy\":\"Hands-on Lab Session, Live Coding Demonstrations\"},{\"week\":6,\"topics\":[\"Integrity Constraints, Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries\",\"Views & Indexes: Creation and Updatability\"],\"pedagogy\":\"Lab-based Query Optimization Exercises, Peer Code Reviews\"},{\"week\":7,\"topics\":[\"Pitfalls in Relational Design, Data Redundancy, Anomalies\",\"Functional Dependencies, Closure of FDs, Armstrong\'s Axioms\"],\"pedagogy\":\"Case-driven Lecture, Mathematical Proof Walkthroughs\"},{\"week\":8,\"topics\":[\"Canonical Cover, First Normal Form (1NF), Second Normal Form (2NF), Third Normal Form (3NF)\"],\"pedagogy\":\"Step-by-step Problem Solving, Flipped Classroom on Anomalies\"},{\"week\":9,\"topics\":[\"Boyce-Codd Normal Form (BCNF), Higher Normal Forms (4NF, 5NF)\",\"Decomposition Properties: Lossless-Join and Dependency Preservation\"],\"pedagogy\":\"Analytical Problem Solving, Peer Instruction\"},{\"week\":10,\"topics\":[\"Transaction Concepts, ACID Properties, Transaction States\",\"Schedules, Serializability (Conflict and View Serializability)\"],\"pedagogy\":\"Visual Timeline Tracing, Real-world Transaction Failure Case Studies\"},{\"week\":11,\"topics\":[\"Recoverability (Cascadeless and Recoverable Schedules)\",\"Lock-Based Protocols: Shared\\/Exclusive Locks, Two-Phase Locking (2PL, Strict 2PL, Rigorous 2PL)\"],\"pedagogy\":\"Interactive Simulation, Scenario-based Analysis\"},{\"week\":12,\"topics\":[\"Deadlocks: Prevention, Detection, and Recovery\",\"Timestamp-Based & Validation-Based Protocols, Thomas\' Write Rule\"],\"pedagogy\":\"Algorithmic Dry-runs, Group Discussion on Deadlock Trade-offs\"},{\"week\":13,\"topics\":[\"Storage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels\",\"Indexing: Primary, Secondary, Clustered, Sparse vs Dense Index\"],\"pedagogy\":\"Comparative Analysis, Architectural Diagrams\"},{\"week\":14,\"topics\":[\"Dynamic Hashing, Tree-Structured Indexing (B-Trees and B+-Trees)\"],\"pedagogy\":\"Animation-based Tutorials, Step-by-step Tree Modification Exercises\"},{\"week\":15,\"topics\":[\"Database Recovery Techniques: Failure Classification, Log-Based Recovery (Deferred & Immediate Modification)\",\"Checkpoints and Shadow Paging\"],\"pedagogy\":\"Trace-based Problem Solving, Course Review, and Mock Exam\"}],\"resources\":[{\"type\":\"Textbook\",\"details\":\"Silberschatz, A., Korth, H. F., & Sudarshan, S. (2020). Database System Concepts (7th ed.). McGraw-Hill.\"},{\"type\":\"Reference Book\",\"details\":\"Elmasri, R., & Navathe, S. B. (2017). Fundamentals of Database Systems (7th ed.). Pearson.\"},{\"type\":\"Online Course\",\"details\":\"NPTEL: \'Database Management Systems\' by Prof. Partha Pratim Das, IIT Kharagpur.\"},{\"type\":\"Tool\",\"details\":\"PostgreSQL Open-Source Relational Database Management System.\"}],\"expert_advice\":[\"Ensure students write SQL queries by hand before executing them in the lab. This builds strong syntax retention and logical query formulation.\",\"Emphasize the mathematical foundations of relational algebra as it directly translates to query optimization in real-world database engines.\",\"When teaching normalization, use realistic, messy business spreadsheets as starting points rather than clean, pre-simplified textbook relations.\",\"Integrate a continuous mini-project where students design, normalize, implement, and query a database for a domain of their choice throughout the semester.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":35,\"K4\":25,\"K5\":10,\"K6\":0},\"ai_score\":96,\"accreditation_template\":\"standard\"}', 1, NULL, '2026-09-29 09:26:54', NULL, NULL, NULL, '{\"accreditation_template\":\"standard\"}', NULL, 0, '2026-09-19 07:55:55', '2026-09-29 03:56:54'),
(2, 1, 1, 5, 2, 1, 'Operating Systems', 'Operating Systems', NULL, 4.0, NULL, NULL, 'Autonomous', 'Unit I: Introduction to Operating Systems\r\nComputer System Overview: Elements, organization, architecture, and multi-core systems.\r\n\r\nOperating System Basics: Objectives, functions, and historical evolution of operating systems.\r\n\r\nSystem Structures: OS services, user interface, system calls, and system programs.\r\n\r\nDesign and Implementation: Structuring methods and virtual machines introduction.\r\n\r\nUnit II: Process Management & Synchronization\r\nProcess Concept: Process scheduling, operations on processes, and Inter-Process Communication (IPC).\r\n\r\nCPU Scheduling: Scheduling criteria and scheduling algorithms (FCFS, SJF, Priority, Round Robin).\r\n\r\nThreads: Multithreaded models and threading issues.\r\n\r\nProcess Synchronization: The critical-section problem, synchronization hardware, semaphores, mutex locks, and classical synchronization problems.\r\n\r\nDeadlocks: Characterization, methods for handling deadlocks, prevention, avoidance, detection, and recovery.\r\n\r\nUnit III: Memory Management\r\nMain Memory: Background, swapping, contiguous memory allocation, and paging.\r\n\r\nPage Tables & Segmentation: Structure of page tables and segmentation with paging.\r\n\r\nVirtual Memory: Demand paging, copy-on-write, page replacement algorithms (FIFO, LRU, Optimal), and allocation of frames.\r\n\r\nPerformance: Thrashing and memory-mapped files.\r\n\r\nUnit IV: Storage Management & I/O Systems\r\nMass-Storage Structure: Disk structure, disk scheduling algorithms, and disk management.\r\n\r\nFile System Interface: File concept, access methods, directory structure, file system mounting, sharing, and protection.\r\n\r\nFile System Implementation: File-system structure, directory implementation, allocation methods (contiguous, linked, indexed), and free-space management.\r\n\r\nI/O Systems: I/O hardware, application I/O interfaces, and kernel I/O subsystems.\r\n\r\nUnit V: Virtualization and Mobile Operating Systems\r\nVirtual Machines: History, benefits, features, building blocks, and types of virtual machines and their implementations.\r\n\r\nVirtualization: OS components and support for virtualization.\r\n\r\nMobile Operating Systems: Architecture, features, and security models of modern mobile operating systems (focused on Android and iOS).', 'approved', 1.95, NULL, '{\"K1\":10,\"K2\":20,\"K3\":25,\"K4\":25,\"K5\":15,\"K6\":5}', '[{\"week\":1,\"topics\":[\"Computer System Elements, Organization, and Architecture\",\"Multi-core Systems and Hardware Support\"],\"pedagogy\":\"Chalk & Talk, Interactive Discussion\"},{\"week\":2,\"topics\":[\"OS Objectives, Functions, and Evolution\",\"OS Services and User Interfaces\"],\"pedagogy\":\"PowerPoint Presentation, Case Study of Early OS\"},{\"week\":3,\"topics\":[\"System Calls, System Programs, and OS Structuring Methods\",\"Introduction to Virtual Machines\"],\"pedagogy\":\"Demonstration of Linux System Calls (strace)\"},{\"week\":4,\"topics\":[\"Process Concept, Process Control Block (PCB), and Scheduling Queues\",\"Operations on Processes and Inter-Process Communication (IPC)\"],\"pedagogy\":\"Collaborative Learning, Coding IPC (Pipes\\/Shared Memory)\"},{\"week\":5,\"topics\":[\"CPU Scheduling Criteria and Non-preemptive Algorithms (FCFS, SJF)\",\"Preemptive Algorithms (SJF, Priority, Round Robin)\"],\"pedagogy\":\"Problem Solving, Algorithm Simulation\"},{\"week\":6,\"topics\":[\"Multithreaded Models, Threading Issues, and Pthreads Library\",\"The Critical-Section Problem and Synchronization Hardware\"],\"pedagogy\":\"Hands-on Lab on Pthreads, Analytical Discussion\"},{\"week\":7,\"topics\":[\"Semaphores, Mutex Locks, and Classical Synchronization Problems\",\"Deadlock Characterization, Prevention, and Avoidance (Banker\'s Algorithm)\"],\"pedagogy\":\"Role Play for Dining Philosophers, Problem Solving\"},{\"week\":8,\"topics\":[\"Deadlock Detection and Recovery\",\"Main Memory Background, Swapping, and Contiguous Allocation\"],\"pedagogy\":\"Interactive Lecture, Numerical Exercises\"},{\"week\":9,\"topics\":[\"Paging: Basic Method, Hardware Support, and Page Table Structure\",\"Segmentation and Segmentation with Paging\"],\"pedagogy\":\"Visual Address Translation Exercises, Flipped Classroom\"},{\"week\":10,\"topics\":[\"Virtual Memory: Demand Paging and Copy-on-Write\",\"Page Replacement Algorithms (FIFO, LRU, Optimal)\"],\"pedagogy\":\"Algorithm Comparison, Graphical Trace Analysis\"},{\"week\":11,\"topics\":[\"Allocation of Frames, Thrashing, and Memory-Mapped Files\",\"Mass-Storage Structure and Disk Scheduling Algorithms\"],\"pedagogy\":\"Problem Solving, Simulation of Disk Scheduling\"},{\"week\":12,\"topics\":[\"Disk Management and Swap-Space Management\",\"File Concept, Access Methods, and Directory Structures\"],\"pedagogy\":\"Case Study of Disk Partitioning, Lectures\"},{\"week\":13,\"topics\":[\"File System Mounting, Protection, and Implementation Structures\",\"Directory Implementation and File Allocation Methods\"],\"pedagogy\":\"Comparative Analysis of Allocation Schemes\"},{\"week\":14,\"topics\":[\"Free-Space Management, I\\/O Hardware, and Kernel I\\/O Subsystem\",\"Virtual Machines: History, Benefits, and Types of Hypervisors\"],\"pedagogy\":\"Technical Seminar, Conceptual Mapping\"},{\"week\":15,\"topics\":[\"OS Support for Virtualization\",\"Mobile OS Architecture, Features, and Android\\/iOS Security Models\"],\"pedagogy\":\"Comparative Case Study, Group Presentation\"}]', '[{\"type\":\"Textbook\",\"details\":\"Abraham Silberschatz, Peter Baer Galvin, and Greg Gagne, \'Operating System Concepts\', 10th Edition, John Wiley & Sons, 2018.\"},{\"type\":\"Textbook\",\"details\":\"William Stallings, \'Operating Systems: Internals and Design Principles\', 9th Edition, Pearson Education, 2018.\"},{\"type\":\"Reference Book\",\"details\":\"Andrew S. Tanenbaum and Herbert Bos, \'Modern Operating Systems\', 4th Edition, Pearson, 2015.\"},{\"type\":\"Online Course\",\"details\":\"NPTEL: \'Operating Systems\' by Prof. Santanu Chattopadhyay, IIT Kharagpur.\"},{\"type\":\"Online Course\",\"details\":\"MIT OpenCourseWare: \'Operating System Engineering\' (6.828).\"}]', '[\"Integrate a mandatory laboratory component using Linux\\/Unix environment to write C programs for system calls, process creation, IPC, and thread synchronization.\",\"Emphasize the practical implications of race conditions and deadlocks using real-world multi-threaded application bugs.\",\"Introduce modern containerization concepts (like Docker) briefly under the virtualization unit to align with current industry standards.\",\"Utilize visualization tools for memory management and disk scheduling to help students grasp physical-to-logical mappings easily.\"]', '{\"title\":\"Operating Systems\",\"learning_outcomes\":[\"CO1: Explain the fundamental structures, components, and evolution of modern operating systems.\",\"CO2: Analyze and implement CPU scheduling, process synchronization, and deadlock prevention\\/avoidance algorithms.\",\"CO3: Evaluate memory management techniques, including paging, segmentation, and virtual memory page replacement policies.\",\"CO4: Design and analyze file systems, directory structures, disk scheduling algorithms, and I\\/O management techniques.\",\"CO5: Compare virtualization architectures, hypervisors, and mobile operating system security models.\",\"CO6: Develop system-level programs to simulate operating system concepts such as scheduling, synchronization, and memory allocation.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to Operating Systems\",\"hours\":11,\"topics\":[\"Computer System Overview: Elements, organization, architecture, and multi-core systems.\",\"Operating System Basics: Objectives, functions, and historical evolution of operating systems.\",\"System Structures: OS services, user interface, system calls, and system programs.\",\"Design and Implementation: Structuring methods and virtual machines introduction.\"],\"outcomes\":[\"Identify the core components and architectural elements of a computer system.\",\"Explain the services, functions, and structural design of modern operating systems.\",\"Differentiate between system calls and system programs, and understand their execution flow.\"],\"bloom_k_level\":\"K2\",\"teaching_methods\":[\"Chalk and Talk\",\"PowerPoint Presentations\",\"Collaborative Learning (Think-Pair-Share on System Calls)\"],\"assessment\":[\"Class Test on OS Structures\",\"Assignment on System Calls tracing in Linux\"]},{\"unit_number\":2,\"title\":\"Process Management & Synchronization\",\"hours\":14,\"topics\":[\"Process Concept: Process scheduling, operations on processes, and Inter-Process Communication (IPC).\",\"CPU Scheduling: Scheduling criteria and scheduling algorithms (FCFS, SJF, Priority, Round Robin).\",\"Threads: Multithreaded models and threading issues.\",\"Process Synchronization: The critical-section problem, synchronization hardware, semaphores, mutex locks, and classical synchronization problems.\",\"Deadlocks: Characterization, methods for handling deadlocks, prevention, avoidance, detection, and recovery.\"],\"outcomes\":[\"Analyze and compare various CPU scheduling algorithms based on performance criteria.\",\"Solve classical synchronization problems using semaphores and mutex locks.\",\"Apply Banker\'s algorithm for deadlock avoidance and analyze deadlock recovery strategies.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Interactive Lectures\",\"Problem-Solving Sessions (Scheduling & Deadlocks)\",\"Coding Demonstrations (POSIX Threads in C)\"],\"assessment\":[\"Mid-Term Examination\",\"Programming Assignment: Simulation of CPU Scheduling Algorithms\",\"Quiz on Process Synchronization\"]},{\"unit_number\":3,\"title\":\"Memory Management\",\"hours\":12,\"topics\":[\"Main Memory: Background, swapping, contiguous memory allocation, and paging.\",\"Page Tables & Segmentation: Structure of page tables and segmentation with paging.\",\"Virtual Memory: Demand paging, copy-on-write, page replacement algorithms (FIFO, LRU, Optimal), and allocation of frames.\",\"Performance: Thrashing and memory-mapped files.\"],\"outcomes\":[\"Contrast contiguous and non-contiguous memory allocation techniques.\",\"Calculate physical addresses from logical addresses using paging and segmentation schemes.\",\"Evaluate and compare the performance of various page replacement algorithms.\"],\"bloom_k_level\":\"K5\",\"teaching_methods\":[\"Flipped Classroom (Paging concepts)\",\"Analytical Problem Solving (Address Translation)\",\"Visual Demonstrations of Page Replacement\"],\"assessment\":[\"Analytical Assignment on Address Translation and Page Tables\",\"Simulation Project: Page Replacement Algorithms\",\"In-class Problem Solving Test\"]},{\"unit_number\":4,\"title\":\"Storage Management & I\\/O Systems\",\"hours\":12,\"topics\":[\"Mass-Storage Structure: Disk structure, disk scheduling algorithms, and disk management.\",\"File System Interface: File concept, access methods, directory structure, file system mounting, sharing, and protection.\",\"File System Implementation: File-system structure, directory implementation, allocation methods (contiguous, linked, indexed), and free-space management.\",\"I\\/O Systems: I\\/O hardware, application I\\/O interfaces, and kernel I\\/O subsystems.\"],\"outcomes\":[\"Analyze and implement disk scheduling algorithms to optimize I\\/O performance.\",\"Compare different directory structures and file allocation methods.\",\"Explain the role of kernel I\\/O subsystems, buffering, and caching.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Lectures\",\"Case Studies of Ext4 and NTFS File Systems\",\"Group Discussion on Disk Scheduling Efficiency\"],\"assessment\":[\"Home Assignment on File Allocation Methods\",\"Written Test on Disk Scheduling Algorithms\",\"Viva-voce on File System Implementation\"]},{\"unit_number\":5,\"title\":\"Virtualization and Mobile Operating Systems\",\"hours\":11,\"topics\":[\"Virtual Machines: History, benefits, features, building blocks, and types of virtual machines and their implementations.\",\"Virtualization: OS components and support for virtualization.\",\"Mobile Operating Systems: Architecture, features, and security models of modern mobile operating systems (focused on Android and iOS).\"],\"outcomes\":[\"Distinguish between Type-1 and Type-2 hypervisors and their deployment scenarios.\",\"Explain the architectural differences between desktop\\/server OS and mobile OS.\",\"Analyze the security models and permission frameworks of Android and iOS.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Technical Seminars\",\"Comparative Case Studies (Android vs. iOS)\",\"Hands-on Demo of VirtualBox\\/VMware\"],\"assessment\":[\"Technical Presentation on Virtualization Technologies\",\"Comparative Report on Mobile OS Security Models\",\"End-Semester Theory Examination\"]}],\"weekly_plan\":[{\"week\":1,\"topics\":[\"Computer System Elements, Organization, and Architecture\",\"Multi-core Systems and Hardware Support\"],\"pedagogy\":\"Chalk & Talk, Interactive Discussion\"},{\"week\":2,\"topics\":[\"OS Objectives, Functions, and Evolution\",\"OS Services and User Interfaces\"],\"pedagogy\":\"PowerPoint Presentation, Case Study of Early OS\"},{\"week\":3,\"topics\":[\"System Calls, System Programs, and OS Structuring Methods\",\"Introduction to Virtual Machines\"],\"pedagogy\":\"Demonstration of Linux System Calls (strace)\"},{\"week\":4,\"topics\":[\"Process Concept, Process Control Block (PCB), and Scheduling Queues\",\"Operations on Processes and Inter-Process Communication (IPC)\"],\"pedagogy\":\"Collaborative Learning, Coding IPC (Pipes\\/Shared Memory)\"},{\"week\":5,\"topics\":[\"CPU Scheduling Criteria and Non-preemptive Algorithms (FCFS, SJF)\",\"Preemptive Algorithms (SJF, Priority, Round Robin)\"],\"pedagogy\":\"Problem Solving, Algorithm Simulation\"},{\"week\":6,\"topics\":[\"Multithreaded Models, Threading Issues, and Pthreads Library\",\"The Critical-Section Problem and Synchronization Hardware\"],\"pedagogy\":\"Hands-on Lab on Pthreads, Analytical Discussion\"},{\"week\":7,\"topics\":[\"Semaphores, Mutex Locks, and Classical Synchronization Problems\",\"Deadlock Characterization, Prevention, and Avoidance (Banker\'s Algorithm)\"],\"pedagogy\":\"Role Play for Dining Philosophers, Problem Solving\"},{\"week\":8,\"topics\":[\"Deadlock Detection and Recovery\",\"Main Memory Background, Swapping, and Contiguous Allocation\"],\"pedagogy\":\"Interactive Lecture, Numerical Exercises\"},{\"week\":9,\"topics\":[\"Paging: Basic Method, Hardware Support, and Page Table Structure\",\"Segmentation and Segmentation with Paging\"],\"pedagogy\":\"Visual Address Translation Exercises, Flipped Classroom\"},{\"week\":10,\"topics\":[\"Virtual Memory: Demand Paging and Copy-on-Write\",\"Page Replacement Algorithms (FIFO, LRU, Optimal)\"],\"pedagogy\":\"Algorithm Comparison, Graphical Trace Analysis\"},{\"week\":11,\"topics\":[\"Allocation of Frames, Thrashing, and Memory-Mapped Files\",\"Mass-Storage Structure and Disk Scheduling Algorithms\"],\"pedagogy\":\"Problem Solving, Simulation of Disk Scheduling\"},{\"week\":12,\"topics\":[\"Disk Management and Swap-Space Management\",\"File Concept, Access Methods, and Directory Structures\"],\"pedagogy\":\"Case Study of Disk Partitioning, Lectures\"},{\"week\":13,\"topics\":[\"File System Mounting, Protection, and Implementation Structures\",\"Directory Implementation and File Allocation Methods\"],\"pedagogy\":\"Comparative Analysis of Allocation Schemes\"},{\"week\":14,\"topics\":[\"Free-Space Management, I\\/O Hardware, and Kernel I\\/O Subsystem\",\"Virtual Machines: History, Benefits, and Types of Hypervisors\"],\"pedagogy\":\"Technical Seminar, Conceptual Mapping\"},{\"week\":15,\"topics\":[\"OS Support for Virtualization\",\"Mobile OS Architecture, Features, and Android\\/iOS Security Models\"],\"pedagogy\":\"Comparative Case Study, Group Presentation\"}],\"resources\":[{\"type\":\"Textbook\",\"details\":\"Abraham Silberschatz, Peter Baer Galvin, and Greg Gagne, \'Operating System Concepts\', 10th Edition, John Wiley & Sons, 2018.\"},{\"type\":\"Textbook\",\"details\":\"William Stallings, \'Operating Systems: Internals and Design Principles\', 9th Edition, Pearson Education, 2018.\"},{\"type\":\"Reference Book\",\"details\":\"Andrew S. Tanenbaum and Herbert Bos, \'Modern Operating Systems\', 4th Edition, Pearson, 2015.\"},{\"type\":\"Online Course\",\"details\":\"NPTEL: \'Operating Systems\' by Prof. Santanu Chattopadhyay, IIT Kharagpur.\"},{\"type\":\"Online Course\",\"details\":\"MIT OpenCourseWare: \'Operating System Engineering\' (6.828).\"}],\"expert_advice\":[\"Integrate a mandatory laboratory component using Linux\\/Unix environment to write C programs for system calls, process creation, IPC, and thread synchronization.\",\"Emphasize the practical implications of race conditions and deadlocks using real-world multi-threaded application bugs.\",\"Introduce modern containerization concepts (like Docker) briefly under the virtualization unit to align with current industry standards.\",\"Utilize visualization tools for memory management and disk scheduling to help students grasp physical-to-logical mappings easily.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":25,\"K4\":25,\"K5\":15,\"K6\":5},\"ai_score\":1.95,\"accreditation_template\":\"standard\"}', 1, NULL, '2026-09-29 09:48:06', '2026-09-29 13:06:44', 2, '{\"overall\":\"\",\"points\":[]}', '{\"accreditation_template\":\"standard\"}', NULL, 0, '2026-09-29 04:15:44', '2026-09-29 07:36:44');

-- --------------------------------------------------------

--
-- Table structure for table `course_plan_versions`
--

CREATE TABLE `course_plan_versions` (
  `id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL,
  `version` int(10) UNSIGNED NOT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot`)),
  `change_note` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_plan_versions`
--

INSERT INTO `course_plan_versions` (`id`, `plan_id`, `version`, `snapshot`, `change_note`, `created_by`, `created_at`) VALUES
(1, 1, 1, '{\"title\":\"Database Management Systems\",\"learning_outcomes\":[\"CO1: Analyze database requirements and design conceptual schemas using Entity-Relationship (ER) and Extended ER (EER) modeling techniques.\",\"CO2: Formulate relational algebra expressions and write optimized Structured Query Language (SQL) queries to perform complex data manipulation and retrieval.\",\"CO3: Apply normalization theory to design logical relational database schemas, eliminating redundancies and anomalies up to Fifth Normal Form (5NF).\",\"CO4: Evaluate transaction schedules for serializability and design concurrency control mechanisms using lock-based and timestamp-based protocols.\",\"CO5: Analyze physical storage structures, implement efficient indexing mechanisms using B\\/B+ Trees, and design database recovery strategies.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to DBMS & Database Architecture\",\"hours\":12,\"topics\":[\"Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS\",\"Data Abstraction, Data Independence (Physical and Logical)\",\"Three-Schema Architecture (Internal, Conceptual, External)\",\"Database Languages (DDL, DML, DCL, TCL), Database Interfaces\",\"Roles of Database Users and DBAs\",\"ER Model: Entities, Attributes, Entity Sets, Relationships, Mapping Cardinalities, Keys (Super, Candidate, Primary, Foreign)\",\"Extended ER (EER) Modeling: Specialization, Generalization, Aggregation\",\"Reduction of ER\\/EER Diagrams to Relational Tables\"],\"outcomes\":[\"Differentiate between traditional file systems and modern DBMS architectures.\",\"Design conceptual schemas using ER and EER diagrams for real-world enterprise scenarios.\",\"Map complex ER\\/EER diagrams into structurally sound relational database tables.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Chalk and Board for architectural diagrams\",\"Collaborative group activity for ER modeling of real-world case studies\",\"Flipped classroom on DBMS vs File Systems\"],\"assessment\":[\"Class Test on ER-to-Relational mapping rules\",\"Evaluation of ER diagram design assignment for a given case study\"]},{\"unit_number\":2,\"title\":\"Relational Model & Query Languages\",\"hours\":12,\"topics\":[\"Structure of Relational Databases, Relational Model Constraints (Domain, Entity Integrity, Referential Integrity)\",\"Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference, Cartesian Product, Rename)\",\"Relational Algebra: Additional Operations (Join, Division, Intersection)\",\"SQL DDL Commands (CREATE, ALTER, DROP, TRUNCATE) and DML Commands (INSERT, UPDATE, DELETE, SELECT)\",\"Integrity Constraints: NOT NULL, UNIQUE, PRIMARY KEY, FOREIGN KEY, CHECK, DEFAULT\",\"Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries, Set Operations, Inner\\/Outer Joins\",\"Views & Indexes: Creating Views, Updatable Views, Index Structures\"],\"outcomes\":[\"Formulate formal relational algebra expressions for complex data retrieval queries.\",\"Construct robust SQL queries utilizing joins, nested subqueries, and aggregation to solve business logic requirements.\",\"Implement and enforce domain, entity, and referential integrity constraints in SQL.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Hands-on laboratory sessions using PostgreSQL\\/MySQL\",\"Problem-solving sessions for Relational Algebra\",\"Interactive live-coding demonstrations\"],\"assessment\":[\"Laboratory practical exam on SQL queries and Joins\",\"Online coding challenge on nested subqueries and constraints\"]},{\"unit_number\":3,\"title\":\"Relational Database Design & Normalization\",\"hours\":12,\"topics\":[\"Pitfalls in Relational Design: Data Redundancy, Insertion, Deletion, and Update Anomalies\",\"Functional Dependencies: Definition, Trivial and Non-trivial Dependencies, Closure of Functional Dependencies, Armstrong\\u2019s Axioms, Canonical Cover\",\"First Normal Form (1NF), Second Normal Form (2NF), Third Normal Form (3NF)\",\"Boyce-Codd Normal Form (BCNF)\",\"Higher Normal Forms: Fourth Normal Form (4NF - Multivalued Dependencies), Fifth Normal Form (5NF - Join Dependencies)\",\"Decomposition Properties: Lossless-Join Decomposition, Dependency-Preserving Decomposition\"],\"outcomes\":[\"Identify and analyze design anomalies in poorly structured relational schemas.\",\"Compute functional dependency closures, canonical covers, and candidate keys for relational schemas.\",\"Decompose relational schemas up to BCNF\\/5NF ensuring lossless-join and dependency-preservation properties.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Problem-driven learning using step-by-step normalization exercises\",\"Peer instruction on decomposition properties\",\"Guided design reviews of student-created schemas\"],\"assessment\":[\"Mid-Semester Examination containing analytical normalization problems\",\"Home assignment on finding Canonical Cover and testing Lossless-Join property\"]},{\"unit_number\":4,\"title\":\"Transaction Management & Concurrency Control\",\"hours\":12,\"topics\":[\"Transaction Concepts: Definition, Transaction States, ACID Properties\",\"Schedules: Concurrent Executions, Serial Schedules, Serializability (Conflict and View Serializability)\",\"Recoverability: Cascadeless and Recoverable Schedules\",\"Lock-Based Protocols: Shared\\/Exclusive Locks, Two-Phase Locking (2PL - Strict 2PL, Rigorous 2PL)\",\"Deadlocks: Deadlock Prevention, Detection, and Recovery\",\"Timestamp-Based & Validation-Based Protocols, Thomas\' Write Rule\"],\"outcomes\":[\"Analyze concurrent transaction schedules for conflict and view serializability.\",\"Apply lock-based and timestamp-based protocols to guarantee database consistency and isolation.\",\"Formulate strategies to prevent, detect, and resolve deadlocks in concurrent transaction environments.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Visual tracing of concurrent execution timelines\",\"Case-study analysis of financial transaction failures\",\"Interactive simulation tools for Lock-based protocols\"],\"assessment\":[\"Quizzes on Serializability and 2PL execution paths\",\"Analytical problem-solving test on Deadlock detection algorithms\"]},{\"unit_number\":5,\"title\":\"Storage Structures, Indexing & Recovery\",\"hours\":12,\"topics\":[\"Storage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels\",\"Indexing & Hashing: Primary, Secondary, Clustered Index, Sparse vs Dense Index\",\"Dynamic Hashing, Tree-Structured Indexing (B-Trees and B+-Trees)\",\"Database Recovery Techniques: Failure Classification (System Crash, Transaction Failure, Disk Failure)\",\"Log-Based Recovery (Deferred Database Modification, Immediate Database Modification)\",\"Checkpoints and Shadow Paging\"],\"outcomes\":[\"Compare different physical file organizations and RAID levels for optimal database performance.\",\"Construct and manipulate B-Trees and B+-Trees for indexing database attributes.\",\"Evaluate recovery algorithms (Deferred vs Immediate modifications) to restore database consistency after failures.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Animation-based demonstration of B+ Tree insertions and deletions\",\"Comparative analysis of RAID levels using real-world performance metrics\",\"Step-by-step walkthrough of log-based recovery scenarios\"],\"assessment\":[\"Assignment on B+ Tree dry-runs (insertion\\/deletion steps)\",\"End-Semester Examination questions on Log-based recovery and Checkpointing\"]}],\"weekly_plan\":[{\"week\":1,\"topics\":[\"Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS\",\"Data Abstraction, Data Independence (Physical and Logical)\"],\"pedagogy\":\"Interactive Lecture & Comparative Discussion\"},{\"week\":2,\"topics\":[\"Three-Schema Architecture, Database Languages (DDL, DML, DCL, TCL), Database Interfaces\",\"Roles of Database Users and DBAs\"],\"pedagogy\":\"Chalk and Board, Role-play activity for DBA vs User roles\"},{\"week\":3,\"topics\":[\"ER Model: Entities, Attributes, Relationships, Keys\",\"EER Modeling: Generalization, Specialization, Aggregation\",\"Reduction of ER\\/EER Diagrams to Relational Tables\"],\"pedagogy\":\"Collaborative Group Design Session, Case Study Analysis\"},{\"week\":4,\"topics\":[\"Structure of Relational Databases, Relational Model Constraints\",\"Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference)\"],\"pedagogy\":\"Problem-Solving Session, Interactive Quizzes\"},{\"week\":5,\"topics\":[\"Relational Algebra: Cartesian Product, Rename, Join, Division, Intersection\",\"SQL DDL & DML Commands, Basic Queries\"],\"pedagogy\":\"Hands-on Lab Session, Live Coding Demonstrations\"},{\"week\":6,\"topics\":[\"Integrity Constraints, Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries\",\"Views & Indexes: Creation and Updatability\"],\"pedagogy\":\"Lab-based Query Optimization Exercises, Peer Code Reviews\"},{\"week\":7,\"topics\":[\"Pitfalls in Relational Design, Data Redundancy, Anomalies\",\"Functional Dependencies, Closure of FDs, Armstrong\'s Axioms\"],\"pedagogy\":\"Case-driven Lecture, Mathematical Proof Walkthroughs\"},{\"week\":8,\"topics\":[\"Canonical Cover, First Normal Form (1NF), Second Normal Form (2NF), Third Normal Form (3NF)\"],\"pedagogy\":\"Step-by-step Problem Solving, Flipped Classroom on Anomalies\"},{\"week\":9,\"topics\":[\"Boyce-Codd Normal Form (BCNF), Higher Normal Forms (4NF, 5NF)\",\"Decomposition Properties: Lossless-Join and Dependency Preservation\"],\"pedagogy\":\"Analytical Problem Solving, Peer Instruction\"},{\"week\":10,\"topics\":[\"Transaction Concepts, ACID Properties, Transaction States\",\"Schedules, Serializability (Conflict and View Serializability)\"],\"pedagogy\":\"Visual Timeline Tracing, Real-world Transaction Failure Case Studies\"},{\"week\":11,\"topics\":[\"Recoverability (Cascadeless and Recoverable Schedules)\",\"Lock-Based Protocols: Shared\\/Exclusive Locks, Two-Phase Locking (2PL, Strict 2PL, Rigorous 2PL)\"],\"pedagogy\":\"Interactive Simulation, Scenario-based Analysis\"},{\"week\":12,\"topics\":[\"Deadlocks: Prevention, Detection, and Recovery\",\"Timestamp-Based & Validation-Based Protocols, Thomas\' Write Rule\"],\"pedagogy\":\"Algorithmic Dry-runs, Group Discussion on Deadlock Trade-offs\"},{\"week\":13,\"topics\":[\"Storage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels\",\"Indexing: Primary, Secondary, Clustered, Sparse vs Dense Index\"],\"pedagogy\":\"Comparative Analysis, Architectural Diagrams\"},{\"week\":14,\"topics\":[\"Dynamic Hashing, Tree-Structured Indexing (B-Trees and B+-Trees)\"],\"pedagogy\":\"Animation-based Tutorials, Step-by-step Tree Modification Exercises\"},{\"week\":15,\"topics\":[\"Database Recovery Techniques: Failure Classification, Log-Based Recovery (Deferred & Immediate Modification)\",\"Checkpoints and Shadow Paging\"],\"pedagogy\":\"Trace-based Problem Solving, Course Review, and Mock Exam\"}],\"resources\":[{\"type\":\"Textbook\",\"details\":\"Silberschatz, A., Korth, H. F., & Sudarshan, S. (2020). Database System Concepts (7th ed.). McGraw-Hill.\"},{\"type\":\"Reference Book\",\"details\":\"Elmasri, R., & Navathe, S. B. (2017). Fundamentals of Database Systems (7th ed.). Pearson.\"},{\"type\":\"Online Course\",\"details\":\"NPTEL: \'Database Management Systems\' by Prof. Partha Pratim Das, IIT Kharagpur.\"},{\"type\":\"Tool\",\"details\":\"PostgreSQL Open-Source Relational Database Management System.\"}],\"expert_advice\":[\"Ensure students write SQL queries by hand before executing them in the lab. This builds strong syntax retention and logical query formulation.\",\"Emphasize the mathematical foundations of relational algebra as it directly translates to query optimization in real-world database engines.\",\"When teaching normalization, use realistic, messy business spreadsheets as starting points rather than clean, pre-simplified textbook relations.\",\"Integrate a continuous mini-project where students design, normalize, implement, and query a database for a domain of their choice throughout the semester.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":35,\"K4\":25,\"K5\":10,\"K6\":0},\"ai_score\":96,\"accreditation_template\":\"standard\"}', 'Initial AI generation · template Standard', 3, '2026-09-19 07:55:55'),
(2, 2, 1, '{\"title\":\"Operating Systems\",\"learning_outcomes\":[\"CO1: Explain the fundamental structures, components, and evolution of modern operating systems.\",\"CO2: Analyze and implement CPU scheduling, process synchronization, and deadlock prevention\\/avoidance algorithms.\",\"CO3: Evaluate memory management techniques, including paging, segmentation, and virtual memory page replacement policies.\",\"CO4: Design and analyze file systems, directory structures, disk scheduling algorithms, and I\\/O management techniques.\",\"CO5: Compare virtualization architectures, hypervisors, and mobile operating system security models.\",\"CO6: Develop system-level programs to simulate operating system concepts such as scheduling, synchronization, and memory allocation.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to Operating Systems\",\"hours\":11,\"topics\":[\"Computer System Overview: Elements, organization, architecture, and multi-core systems.\",\"Operating System Basics: Objectives, functions, and historical evolution of operating systems.\",\"System Structures: OS services, user interface, system calls, and system programs.\",\"Design and Implementation: Structuring methods and virtual machines introduction.\"],\"outcomes\":[\"Identify the core components and architectural elements of a computer system.\",\"Explain the services, functions, and structural design of modern operating systems.\",\"Differentiate between system calls and system programs, and understand their execution flow.\"],\"bloom_k_level\":\"K2\",\"teaching_methods\":[\"Chalk and Talk\",\"PowerPoint Presentations\",\"Collaborative Learning (Think-Pair-Share on System Calls)\"],\"assessment\":[\"Class Test on OS Structures\",\"Assignment on System Calls tracing in Linux\"]},{\"unit_number\":2,\"title\":\"Process Management & Synchronization\",\"hours\":14,\"topics\":[\"Process Concept: Process scheduling, operations on processes, and Inter-Process Communication (IPC).\",\"CPU Scheduling: Scheduling criteria and scheduling algorithms (FCFS, SJF, Priority, Round Robin).\",\"Threads: Multithreaded models and threading issues.\",\"Process Synchronization: The critical-section problem, synchronization hardware, semaphores, mutex locks, and classical synchronization problems.\",\"Deadlocks: Characterization, methods for handling deadlocks, prevention, avoidance, detection, and recovery.\"],\"outcomes\":[\"Analyze and compare various CPU scheduling algorithms based on performance criteria.\",\"Solve classical synchronization problems using semaphores and mutex locks.\",\"Apply Banker\'s algorithm for deadlock avoidance and analyze deadlock recovery strategies.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Interactive Lectures\",\"Problem-Solving Sessions (Scheduling & Deadlocks)\",\"Coding Demonstrations (POSIX Threads in C)\"],\"assessment\":[\"Mid-Term Examination\",\"Programming Assignment: Simulation of CPU Scheduling Algorithms\",\"Quiz on Process Synchronization\"]},{\"unit_number\":3,\"title\":\"Memory Management\",\"hours\":12,\"topics\":[\"Main Memory: Background, swapping, contiguous memory allocation, and paging.\",\"Page Tables & Segmentation: Structure of page tables and segmentation with paging.\",\"Virtual Memory: Demand paging, copy-on-write, page replacement algorithms (FIFO, LRU, Optimal), and allocation of frames.\",\"Performance: Thrashing and memory-mapped files.\"],\"outcomes\":[\"Contrast contiguous and non-contiguous memory allocation techniques.\",\"Calculate physical addresses from logical addresses using paging and segmentation schemes.\",\"Evaluate and compare the performance of various page replacement algorithms.\"],\"bloom_k_level\":\"K5\",\"teaching_methods\":[\"Flipped Classroom (Paging concepts)\",\"Analytical Problem Solving (Address Translation)\",\"Visual Demonstrations of Page Replacement\"],\"assessment\":[\"Analytical Assignment on Address Translation and Page Tables\",\"Simulation Project: Page Replacement Algorithms\",\"In-class Problem Solving Test\"]},{\"unit_number\":4,\"title\":\"Storage Management & I\\/O Systems\",\"hours\":12,\"topics\":[\"Mass-Storage Structure: Disk structure, disk scheduling algorithms, and disk management.\",\"File System Interface: File concept, access methods, directory structure, file system mounting, sharing, and protection.\",\"File System Implementation: File-system structure, directory implementation, allocation methods (contiguous, linked, indexed), and free-space management.\",\"I\\/O Systems: I\\/O hardware, application I\\/O interfaces, and kernel I\\/O subsystems.\"],\"outcomes\":[\"Analyze and implement disk scheduling algorithms to optimize I\\/O performance.\",\"Compare different directory structures and file allocation methods.\",\"Explain the role of kernel I\\/O subsystems, buffering, and caching.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Lectures\",\"Case Studies of Ext4 and NTFS File Systems\",\"Group Discussion on Disk Scheduling Efficiency\"],\"assessment\":[\"Home Assignment on File Allocation Methods\",\"Written Test on Disk Scheduling Algorithms\",\"Viva-voce on File System Implementation\"]},{\"unit_number\":5,\"title\":\"Virtualization and Mobile Operating Systems\",\"hours\":11,\"topics\":[\"Virtual Machines: History, benefits, features, building blocks, and types of virtual machines and their implementations.\",\"Virtualization: OS components and support for virtualization.\",\"Mobile Operating Systems: Architecture, features, and security models of modern mobile operating systems (focused on Android and iOS).\"],\"outcomes\":[\"Distinguish between Type-1 and Type-2 hypervisors and their deployment scenarios.\",\"Explain the architectural differences between desktop\\/server OS and mobile OS.\",\"Analyze the security models and permission frameworks of Android and iOS.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Technical Seminars\",\"Comparative Case Studies (Android vs. iOS)\",\"Hands-on Demo of VirtualBox\\/VMware\"],\"assessment\":[\"Technical Presentation on Virtualization Technologies\",\"Comparative Report on Mobile OS Security Models\",\"End-Semester Theory Examination\"]}],\"weekly_plan\":[{\"week\":1,\"topics\":[\"Computer System Elements, Organization, and Architecture\",\"Multi-core Systems and Hardware Support\"],\"pedagogy\":\"Chalk & Talk, Interactive Discussion\"},{\"week\":2,\"topics\":[\"OS Objectives, Functions, and Evolution\",\"OS Services and User Interfaces\"],\"pedagogy\":\"PowerPoint Presentation, Case Study of Early OS\"},{\"week\":3,\"topics\":[\"System Calls, System Programs, and OS Structuring Methods\",\"Introduction to Virtual Machines\"],\"pedagogy\":\"Demonstration of Linux System Calls (strace)\"},{\"week\":4,\"topics\":[\"Process Concept, Process Control Block (PCB), and Scheduling Queues\",\"Operations on Processes and Inter-Process Communication (IPC)\"],\"pedagogy\":\"Collaborative Learning, Coding IPC (Pipes\\/Shared Memory)\"},{\"week\":5,\"topics\":[\"CPU Scheduling Criteria and Non-preemptive Algorithms (FCFS, SJF)\",\"Preemptive Algorithms (SJF, Priority, Round Robin)\"],\"pedagogy\":\"Problem Solving, Algorithm Simulation\"},{\"week\":6,\"topics\":[\"Multithreaded Models, Threading Issues, and Pthreads Library\",\"The Critical-Section Problem and Synchronization Hardware\"],\"pedagogy\":\"Hands-on Lab on Pthreads, Analytical Discussion\"},{\"week\":7,\"topics\":[\"Semaphores, Mutex Locks, and Classical Synchronization Problems\",\"Deadlock Characterization, Prevention, and Avoidance (Banker\'s Algorithm)\"],\"pedagogy\":\"Role Play for Dining Philosophers, Problem Solving\"},{\"week\":8,\"topics\":[\"Deadlock Detection and Recovery\",\"Main Memory Background, Swapping, and Contiguous Allocation\"],\"pedagogy\":\"Interactive Lecture, Numerical Exercises\"},{\"week\":9,\"topics\":[\"Paging: Basic Method, Hardware Support, and Page Table Structure\",\"Segmentation and Segmentation with Paging\"],\"pedagogy\":\"Visual Address Translation Exercises, Flipped Classroom\"},{\"week\":10,\"topics\":[\"Virtual Memory: Demand Paging and Copy-on-Write\",\"Page Replacement Algorithms (FIFO, LRU, Optimal)\"],\"pedagogy\":\"Algorithm Comparison, Graphical Trace Analysis\"},{\"week\":11,\"topics\":[\"Allocation of Frames, Thrashing, and Memory-Mapped Files\",\"Mass-Storage Structure and Disk Scheduling Algorithms\"],\"pedagogy\":\"Problem Solving, Simulation of Disk Scheduling\"},{\"week\":12,\"topics\":[\"Disk Management and Swap-Space Management\",\"File Concept, Access Methods, and Directory Structures\"],\"pedagogy\":\"Case Study of Disk Partitioning, Lectures\"},{\"week\":13,\"topics\":[\"File System Mounting, Protection, and Implementation Structures\",\"Directory Implementation and File Allocation Methods\"],\"pedagogy\":\"Comparative Analysis of Allocation Schemes\"},{\"week\":14,\"topics\":[\"Free-Space Management, I\\/O Hardware, and Kernel I\\/O Subsystem\",\"Virtual Machines: History, Benefits, and Types of Hypervisors\"],\"pedagogy\":\"Technical Seminar, Conceptual Mapping\"},{\"week\":15,\"topics\":[\"OS Support for Virtualization\",\"Mobile OS Architecture, Features, and Android\\/iOS Security Models\"],\"pedagogy\":\"Comparative Case Study, Group Presentation\"}],\"resources\":[{\"type\":\"Textbook\",\"details\":\"Abraham Silberschatz, Peter Baer Galvin, and Greg Gagne, \'Operating System Concepts\', 10th Edition, John Wiley & Sons, 2018.\"},{\"type\":\"Textbook\",\"details\":\"William Stallings, \'Operating Systems: Internals and Design Principles\', 9th Edition, Pearson Education, 2018.\"},{\"type\":\"Reference Book\",\"details\":\"Andrew S. Tanenbaum and Herbert Bos, \'Modern Operating Systems\', 4th Edition, Pearson, 2015.\"},{\"type\":\"Online Course\",\"details\":\"NPTEL: \'Operating Systems\' by Prof. Santanu Chattopadhyay, IIT Kharagpur.\"},{\"type\":\"Online Course\",\"details\":\"MIT OpenCourseWare: \'Operating System Engineering\' (6.828).\"}],\"expert_advice\":[\"Integrate a mandatory laboratory component using Linux\\/Unix environment to write C programs for system calls, process creation, IPC, and thread synchronization.\",\"Emphasize the practical implications of race conditions and deadlocks using real-world multi-threaded application bugs.\",\"Introduce modern containerization concepts (like Docker) briefly under the virtualization unit to align with current industry standards.\",\"Utilize visualization tools for memory management and disk scheduling to help students grasp physical-to-logical mappings easily.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":25,\"K4\":25,\"K5\":15,\"K6\":5},\"ai_score\":1.95,\"accreditation_template\":\"standard\"}', 'Initial AI generation · template Standard', 5, '2026-09-29 04:15:44');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `hod_user_id` int(10) UNSIGNED DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `institution_id`, `name`, `code`, `hod_user_id`, `meta`, `is_active`, `created_at`) VALUES
(1, 1, 'Computer Science and Engineering', 'CSE01', 2, NULL, 1, '2026-09-19 07:48:32'),
(2, 1, 'Electronics and Communication Engineering', 'ECE', 6, NULL, 1, '2026-09-30 03:31:01');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `doc_type` enum('note','ppt','syllabus','circular','naac','other') DEFAULT 'note',
  `title` varchar(255) NOT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `unit_number` int(10) UNSIGNED DEFAULT NULL,
  `content_text` longtext DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_chunks`
--

CREATE TABLE `document_chunks` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `chunk_index` int(10) UNSIGNED NOT NULL,
  `content_chunk` text NOT NULL,
  `embedding_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Gemini embedding vector (expandable)' CHECK (json_valid(`embedding_json`)),
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` varchar(40) DEFAULT NULL,
  `status` enum('active','dropped','completed') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `subject_id`, `class_id`, `academic_year`, `semester`, `status`) VALUES
(1, 4, 1, 1, '2026-27', 'Odd Semester', 'active'),
(2, 4, 2, 1, '2026-27', 'Odd Semester', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `exam_papers`
--

CREATE TABLE `exam_papers` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `total_marks` decimal(6,1) NOT NULL DEFAULT 50.0,
  `config` longtext DEFAULT NULL,
  `sets_data` longtext DEFAULT NULL,
  `answer_key` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(200) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `vendor` varchar(150) DEFAULT NULL,
  `payment_mode` varchar(40) DEFAULT NULL,
  `added_by` int(10) UNSIGNED NOT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feature_flags`
--

CREATE TABLE `feature_flags` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(64) NOT NULL DEFAULT 'core',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `default_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feature_flags`
--

INSERT INTO `feature_flags` (`id`, `code`, `name`, `description`, `module`, `is_enabled`, `default_config`, `created_at`, `updated_at`) VALUES
(1, 'ai_course_plan', 'AI Course Plan Generator', 'Generate structured course plans from syllabus', 'professor', 1, '{\"model\": \"gemini-2.0-flash\"}', '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(2, 'bloom_mapper', 'Bloom\'s Taxonomy Auto-Mapper', 'Map units/topics to K1-K6', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(3, 'ai_review', 'AI Curriculum Review', '12-parameter quality review', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(4, 'improve_ai', 'Improve with AI', 'Instruction-based plan improvement', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(5, 'lesson_planner', 'AI Lesson Planner', 'Session-wise lesson plans', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(6, 'question_bank', 'Question Bank Generator', 'MCQ / short / long by K-level', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(7, 'ppt_generator', 'AI PPT Generator', 'Generate presentation slides', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(8, 'assignment_ai', 'AI Assignment Generator', 'Multi-type assignments + rubrics', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(9, 'attendance', 'Smart Attendance', 'Attendance with 75% alerts', 'professor', 1, '{\"min_pct\": 75}', '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(10, 'internal_marks', 'Configurable Internal Marks', 'Formula-driven CIA marks', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(11, 'version_control', 'Plan Version Control', 'Draft to approved workflow', 'professor', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(12, 'notifications', 'Notifications Centre', 'In-app notifications', 'core', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(13, 'student_portal', 'Student Portal', 'Courses, notes, submissions', 'student', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(14, 'ask_ai', 'Ask AI Study Assistant', 'RAG-style study chatbot', 'student', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(15, 'hod_approvals', 'HOD Approvals', 'Course plan review queue', 'hod', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(16, 'dept_analytics', 'Department Analytics', 'Bloom & quality analytics', 'hod', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(17, 'naac_reports', 'NAAC/NBA Reports', 'Accreditation document builder', 'admin', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(18, 'finance', 'Finance & Expenses', 'Operational cost tracking', 'admin', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(19, 'user_management', 'Role & User Management', 'Bulk import & roles', 'admin', 1, NULL, '2026-08-08 17:07:02', '2026-08-08 17:07:02'),
(20, 'api_hub', 'API & Integration Hub', 'External integrations', 'admin', 0, '{\"coming_soon\": true}', '2026-08-08 17:07:02', '2026-08-08 17:07:02');

-- --------------------------------------------------------

--
-- Table structure for table `institutions`
--

CREATE TABLE `institutions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `affiliation_university` varchar(200) DEFAULT NULL,
  `naac_grade` varchar(20) DEFAULT NULL,
  `nba_status` varchar(40) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `pincode` varchar(12) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `subscription_tier` enum('starter','professional','enterprise','trial') DEFAULT 'trial',
  `licensed_seats` int(10) UNSIGNED DEFAULT 60,
  `academic_year` varchar(20) DEFAULT NULL,
  `current_semester` varchar(40) DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Expandable institution settings' CHECK (json_valid(`settings`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `institutions`
--

INSERT INTO `institutions` (`id`, `name`, `code`, `affiliation_university`, `naac_grade`, `nba_status`, `address`, `city`, `state`, `pincode`, `phone`, `email`, `logo_url`, `subscription_tier`, `licensed_seats`, `academic_year`, `current_semester`, `settings`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'K.L.N College of Engineering', NULL, 'Autonomous', 'A', NULL, NULL, 'madurai', 'Tamilnadu', NULL, NULL, NULL, 'https://klnce.edu/images/Common_wrapper4.gif', 'trial', 60, '2026-27', 'Odd', '{\"attendance_min\":75,\"brand_primary\":\"1E3A8A\",\"brand_secondary\":\"0F172A\",\"brand_accent\":\"D97706\",\"geofence_required_for_qr\":false}', 1, '2026-08-08 17:07:02', '2026-09-29 05:23:29');

-- --------------------------------------------------------

--
-- Table structure for table `institution_features`
--

CREATE TABLE `institution_features` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `feature_code` varchar(64) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `enabled_at` timestamp NULL DEFAULT NULL,
  `disabled_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `institution_features`
--

INSERT INTO `institution_features` (`id`, `institution_id`, `feature_code`, `is_enabled`, `config`, `enabled_at`, `disabled_at`) VALUES
(1, 1, 'ai_course_plan', 1, '{\"model\": \"gemini-2.0-flash\"}', '2026-08-08 17:07:02', NULL),
(2, 1, 'bloom_mapper', 1, NULL, '2026-08-08 17:07:02', NULL),
(3, 1, 'ai_review', 1, NULL, '2026-08-08 17:07:02', NULL),
(4, 1, 'improve_ai', 1, NULL, '2026-08-08 17:07:02', NULL),
(5, 1, 'lesson_planner', 1, NULL, '2026-08-08 17:07:02', NULL),
(6, 1, 'question_bank', 1, NULL, '2026-08-08 17:07:02', NULL),
(7, 1, 'ppt_generator', 1, NULL, '2026-08-08 17:07:02', NULL),
(8, 1, 'assignment_ai', 1, NULL, '2026-08-08 17:07:02', NULL),
(9, 1, 'attendance', 1, '{\"min_pct\": 75}', '2026-08-08 17:07:02', NULL),
(10, 1, 'internal_marks', 1, NULL, NULL, '2026-08-12 07:43:15'),
(11, 1, 'version_control', 1, NULL, '2026-08-08 17:07:02', NULL),
(12, 1, 'notifications', 1, NULL, '2026-08-08 17:07:02', NULL),
(13, 1, 'student_portal', 1, NULL, '2026-08-08 17:07:02', NULL),
(14, 1, 'ask_ai', 1, NULL, '2026-08-08 17:07:02', NULL),
(15, 1, 'hod_approvals', 1, NULL, '2026-08-08 17:07:02', NULL),
(16, 1, 'dept_analytics', 1, NULL, '2026-08-08 17:07:02', NULL),
(17, 1, 'naac_reports', 1, NULL, '2026-08-08 17:07:02', NULL),
(18, 1, 'finance', 1, NULL, '2026-08-08 17:07:02', NULL),
(19, 1, 'user_management', 1, NULL, '2026-08-08 17:07:02', NULL),
(20, 1, 'api_hub', 1, NULL, '2026-08-21 10:58:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `internal_marks`
--

CREATE TABLE `internal_marks` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `academic_year` varchar(20) NOT NULL DEFAULT '' COMMENT 'Institution academic year snapshot',
  `formula_id` int(10) UNSIGNED DEFAULT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `register_no` varchar(60) NOT NULL,
  `student_name` varchar(160) NOT NULL,
  `marks_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'component => value' CHECK (json_valid(`marks_data`)),
  `attendance_pct` decimal(5,2) DEFAULT NULL,
  `assignment_total` decimal(6,2) DEFAULT NULL,
  `computed_total` decimal(6,2) DEFAULT NULL,
  `grade_letter` varchar(5) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `internal_marks`
--

INSERT INTO `internal_marks` (`id`, `institution_id`, `professor_id`, `subject_id`, `class_id`, `academic_year`, `formula_id`, `student_id`, `register_no`, `student_name`, `marks_data`, `attendance_pct`, `assignment_total`, `computed_total`, `grade_letter`, `meta`, `updated_at`) VALUES
(1, 1, 3, 1, 1, '2026-27', NULL, 4, '224026', 'Mohammed Abuthahir', '{\"cia1\":40,\"cia2\":40}', 100.00, NULL, 20.00, 'A', '{\"academic_year\":\"2026-27\",\"formula_name\":\"CBCS fallback · CIA average to 25\",\"formula_expression\":\"((cia1+cia2)\\/2)*(25\\/50)\",\"total_max\":25,\"components\":[{\"code\":\"cia1\",\"label\":\"CIA 1\",\"max\":50},{\"code\":\"cia2\",\"label\":\"CIA 2\",\"max\":50}]}', '2026-09-30 05:01:20'),
(2, 1, 5, 2, 1, '2026-27', NULL, 4, '224026', 'Mohammed Abuthahir', '{\"cia1\":40,\"cia2\":20}', 0.00, NULL, 15.00, 'C', '{\"academic_year\":\"2026-27\",\"formula_name\":\"CBCS fallback · CIA average to 25\",\"formula_expression\":\"((cia1+cia2)\\/2)*(25\\/50)\",\"total_max\":25,\"components\":[{\"code\":\"cia1\",\"label\":\"CIA 1\",\"max\":50},{\"code\":\"cia2\",\"label\":\"CIA 2\",\"max\":50}]}', '2026-09-30 05:18:18');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_plans`
--

CREATE TABLE `lesson_plans` (
  `id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED DEFAULT NULL,
  `session_number` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `duration_mins` int(10) UNSIGNED DEFAULT 60,
  `objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`objectives`)),
  `teaching_method` varchar(120) DEFAULT NULL,
  `activities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`activities`)),
  `formative_assessment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`formative_assessment`)),
  `engagement` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`engagement`)),
  `materials` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`materials`)),
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`content`)),
  `session_date` date DEFAULT NULL,
  `session_status` varchar(20) NOT NULL DEFAULT 'planned' COMMENT 'planned|completed|delayed',
  `planned_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `bloom_k_level` varchar(10) DEFAULT NULL,
  `unit_number` int(10) UNSIGNED DEFAULT NULL,
  `suggested_method` varchar(200) DEFAULT NULL,
  `resources` longtext DEFAULT NULL COMMENT 'JSON resource suggestions',
  `calendar_event_id` int(10) UNSIGNED DEFAULT NULL,
  `period_label` varchar(40) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lesson_plans`
--

INSERT INTO `lesson_plans` (`id`, `plan_id`, `professor_id`, `unit_id`, `session_number`, `title`, `duration_mins`, `objectives`, `teaching_method`, `activities`, `formative_assessment`, `engagement`, `materials`, `content`, `session_date`, `session_status`, `planned_date`, `actual_date`, `bloom_k_level`, `unit_number`, `suggested_method`, `resources`, `calendar_event_id`, `period_label`, `meta`, `created_at`) VALUES
(1, 1, 3, NULL, 1, 'Data vs Information & Evolution of DBMS', 60, '[\"Distinguish between raw data and structured information.\",\"Explain the historical evolution from manual record-keeping to computerized file systems.\"]', 'Flipped classroom and interactive lecture', '[\"Group discussion comparing a physical ledger with a digital spreadsheet.\"]', '[\"One-minute paper summarizing why data requires context to become information.\"]', '[\"Think-Pair-Share: Ask students to identify three daily activities that generate data.\"]', NULL, '{\"session_number\":1,\"unit_id\":null,\"title\":\"Data vs Information & Evolution of DBMS\",\"duration_mins\":60,\"objectives\":[\"Distinguish between raw data and structured information.\",\"Explain the historical evolution from manual record-keeping to computerized file systems.\"],\"teaching_method\":\"Flipped classroom and interactive lecture\",\"activities\":[\"Group discussion comparing a physical ledger with a digital spreadsheet.\"],\"formative_assessment\":[\"One-minute paper summarizing why data requires context to become information.\"],\"engagement\":[\"Think-Pair-Share: Ask students to identify three daily activities that generate data.\"]}', NULL, 'completed', NULL, '2026-09-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(2, 1, 3, NULL, 2, 'File-Processing Systems vs DBMS & Advantages of DBMS', 60, '[\"Identify the limitations of traditional file-processing systems.\",\"Explain how DBMS solves data redundancy, inconsistency, and security issues.\"]', 'Comparative analysis and case-driven lecture', '[\"Analyze a scenario of a university using separate text files for student records and tuition payments.\"]', '[\"A quick 3-question poll on file system anomalies.\"]', '[\"Role-play: Students act as different departments trying to update a shared student address in isolated files.\"]', NULL, '{\"session_number\":2,\"unit_id\":null,\"title\":\"File-Processing Systems vs DBMS & Advantages of DBMS\",\"duration_mins\":60,\"objectives\":[\"Identify the limitations of traditional file-processing systems.\",\"Explain how DBMS solves data redundancy, inconsistency, and security issues.\"],\"teaching_method\":\"Comparative analysis and case-driven lecture\",\"activities\":[\"Analyze a scenario of a university using separate text files for student records and tuition payments.\"],\"formative_assessment\":[\"A quick 3-question poll on file system anomalies.\"],\"engagement\":[\"Role-play: Students act as different departments trying to update a shared student address in isolated files.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(3, 1, 3, NULL, 3, 'Data Abstraction and Data Independence', 60, '[\"Explain the three levels of data abstraction.\",\"Differentiate between physical and logical data independence.\"]', 'Chalk and Board with visual diagrams', '[\"Draw and label the abstraction layers for a sample e-commerce application.\"]', '[\"Spot-check questions asking students to categorize changes as physical or logical.\"]', '[\"Analogy mapping: Comparing database abstraction to driving a car without knowing engine mechanics.\"]', NULL, '{\"session_number\":3,\"unit_id\":null,\"title\":\"Data Abstraction and Data Independence\",\"duration_mins\":60,\"objectives\":[\"Explain the three levels of data abstraction.\",\"Differentiate between physical and logical data independence.\"],\"teaching_method\":\"Chalk and Board with visual diagrams\",\"activities\":[\"Draw and label the abstraction layers for a sample e-commerce application.\"],\"formative_assessment\":[\"Spot-check questions asking students to categorize changes as physical or logical.\"],\"engagement\":[\"Analogy mapping: Comparing database abstraction to driving a car without knowing engine mechanics.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(4, 1, 3, NULL, 4, 'Three-Schema Architecture', 60, '[\"Describe the purpose of the ANSI-SPARC Three-Schema Architecture.\",\"Explain how schema mapping supports data independence.\"]', 'Interactive lecture with architectural walkthroughs', '[\"Trace a query from the external view down to physical storage blocks.\"]', '[\"Sketching the three-schema architecture from memory with correct labels.\"]', '[\"Peer instruction: Explain to your neighbor why the conceptual schema is the heart of the architecture.\"]', NULL, '{\"session_number\":4,\"unit_id\":null,\"title\":\"Three-Schema Architecture\",\"duration_mins\":60,\"objectives\":[\"Describe the purpose of the ANSI-SPARC Three-Schema Architecture.\",\"Explain how schema mapping supports data independence.\"],\"teaching_method\":\"Interactive lecture with architectural walkthroughs\",\"activities\":[\"Trace a query from the external view down to physical storage blocks.\"],\"formative_assessment\":[\"Sketching the three-schema architecture from memory with correct labels.\"],\"engagement\":[\"Peer instruction: Explain to your neighbor why the conceptual schema is the heart of the architecture.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(5, 1, 3, NULL, 5, 'Database Languages (DDL, DML, DCL, TCL) & Interfaces', 60, '[\"Categorize SQL statements into DDL, DML, DCL, and TCL.\",\"Identify different types of database interfaces.\"]', 'Interactive categorization and live syntax demonstration', '[\"Sort a mixed list of SQL commands into their respective sub-language categories.\"]', '[\"Clicker quiz on classifying commands like \'GRANT\', \'COMMIT\', and \'ALTER\'.\"]', '[\"Speed-sorting game: Teams compete to classify commands on a shared virtual board.\"]', NULL, '{\"session_number\":5,\"unit_id\":null,\"title\":\"Database Languages (DDL, DML, DCL, TCL) & Interfaces\",\"duration_mins\":60,\"objectives\":[\"Categorize SQL statements into DDL, DML, DCL, and TCL.\",\"Identify different types of database interfaces.\"],\"teaching_method\":\"Interactive categorization and live syntax demonstration\",\"activities\":[\"Sort a mixed list of SQL commands into their respective sub-language categories.\"],\"formative_assessment\":[\"Clicker quiz on classifying commands like \'GRANT\', \'COMMIT\', and \'ALTER\'.\"],\"engagement\":[\"Speed-sorting game: Teams compete to classify commands on a shared virtual board.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(6, 1, 3, NULL, 6, 'Roles of Database Users and DBAs', 60, '[\"Identify the responsibilities of Database Administrators (DBAs).\",\"Differentiate between naive users, application programmers, and sophisticated users.\"]', 'Role-play and group discussion', '[\"Simulate a database crash scenario and assign recovery tasks to appropriate roles.\"]', '[\"Match-the-following worksheet pairing user types with their typical database interactions.\"]', '[\"Mock job interview: Students interview each other for a DBA role based on a list of responsibilities.\"]', NULL, '{\"session_number\":6,\"unit_id\":null,\"title\":\"Roles of Database Users and DBAs\",\"duration_mins\":60,\"objectives\":[\"Identify the responsibilities of Database Administrators (DBAs).\",\"Differentiate between naive users, application programmers, and sophisticated users.\"],\"teaching_method\":\"Role-play and group discussion\",\"activities\":[\"Simulate a database crash scenario and assign recovery tasks to appropriate roles.\"],\"formative_assessment\":[\"Match-the-following worksheet pairing user types with their typical database interactions.\"],\"engagement\":[\"Mock job interview: Students interview each other for a DBA role based on a list of responsibilities.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(7, 1, 3, NULL, 7, 'ER Model: Entities, Attributes, and Keys', 60, '[\"Define entities, attributes, and entity sets.\",\"Distinguish between primary, candidate, super, and foreign keys.\"]', 'Chalk and Board with step-by-step definition building', '[\"Analyze a simple scenario to identify candidate keys and select the best primary key.\"]', '[\"Short quiz on identifying candidate keys from a given set of functional properties.\"]', '[\"Brainstorming: List all possible attributes for a \'Car\' entity and classify them (simple vs. composite).\"]', NULL, '{\"session_number\":7,\"unit_id\":null,\"title\":\"ER Model: Entities, Attributes, and Keys\",\"duration_mins\":60,\"objectives\":[\"Define entities, attributes, and entity sets.\",\"Distinguish between primary, candidate, super, and foreign keys.\"],\"teaching_method\":\"Chalk and Board with step-by-step definition building\",\"activities\":[\"Analyze a simple scenario to identify candidate keys and select the best primary key.\"],\"formative_assessment\":[\"Short quiz on identifying candidate keys from a given set of functional properties.\"],\"engagement\":[\"Brainstorming: List all possible attributes for a \'Car\' entity and classify them (simple vs. composite).\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(8, 1, 3, NULL, 8, 'ER Model: Relationships and Mapping Cardinalities', 60, '[\"Model relationships between entities with appropriate structural constraints.\",\"Determine mapping cardinalities (1:1, 1:N, N:M) for real-world scenarios.\"]', 'Collaborative group activity and case study analysis', '[\"Draw ER fragments for a hospital management system (Doctors, Patients, Appointments).\"]', '[\"Peer evaluation of ER relationship diagrams drawn by other groups.\"]', '[\"Debate: Discuss whether a \'Student-Course\' relationship should be 1:N or N:M under different school policies.\"]', NULL, '{\"session_number\":8,\"unit_id\":null,\"title\":\"ER Model: Relationships and Mapping Cardinalities\",\"duration_mins\":60,\"objectives\":[\"Model relationships between entities with appropriate structural constraints.\",\"Determine mapping cardinalities (1:1, 1:N, N:M) for real-world scenarios.\"],\"teaching_method\":\"Collaborative group activity and case study analysis\",\"activities\":[\"Draw ER fragments for a hospital management system (Doctors, Patients, Appointments).\"],\"formative_assessment\":[\"Peer evaluation of ER relationship diagrams drawn by other groups.\"],\"engagement\":[\"Debate: Discuss whether a \'Student-Course\' relationship should be 1:N or N:M under different school policies.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(9, 1, 3, NULL, 9, 'Extended ER (EER) Modeling: Specialization and Generalization', 60, '[\"Apply specialization and generalization to represent inheritance hierarchies.\",\"Enforce completeness and disjointness constraints on specialization.\"]', 'Problem-driven learning with hierarchical diagrams', '[\"Design an EER diagram for a bank account system with Savings and Checking accounts.\"]', '[\"Classifying a given hierarchy as total\\/partial and disjoint\\/overlapping.\"]', '[\"Object-oriented analogy: Comparing EER specialization to class inheritance in Java\\/C++.\"]', NULL, '{\"session_number\":9,\"unit_id\":null,\"title\":\"Extended ER (EER) Modeling: Specialization and Generalization\",\"duration_mins\":60,\"objectives\":[\"Apply specialization and generalization to represent inheritance hierarchies.\",\"Enforce completeness and disjointness constraints on specialization.\"],\"teaching_method\":\"Problem-driven learning with hierarchical diagrams\",\"activities\":[\"Design an EER diagram for a bank account system with Savings and Checking accounts.\"],\"formative_assessment\":[\"Classifying a given hierarchy as total\\/partial and disjoint\\/overlapping.\"],\"engagement\":[\"Object-oriented analogy: Comparing EER specialization to class inheritance in Java\\/C++.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(10, 1, 3, NULL, 10, 'Extended ER (EER) Modeling: Aggregation', 60, '[\"Explain the concept of aggregation in EER modeling.\",\"Identify scenarios where aggregation is required over ternary relationships.\"]', 'Interactive lecture and comparative modeling', '[\"Model a scenario where a manager supervises a specific project-employee assignment.\"]', '[\"Draw-along exercise: Correcting a flawed ternary relationship using aggregation.\"]', '[\"Problem-solving challenge: \'Why can\'t we just use a regular relationship here?\' group discussion.\"]', NULL, '{\"session_number\":10,\"unit_id\":null,\"title\":\"Extended ER (EER) Modeling: Aggregation\",\"duration_mins\":60,\"objectives\":[\"Explain the concept of aggregation in EER modeling.\",\"Identify scenarios where aggregation is required over ternary relationships.\"],\"teaching_method\":\"Interactive lecture and comparative modeling\",\"activities\":[\"Model a scenario where a manager supervises a specific project-employee assignment.\"],\"formative_assessment\":[\"Draw-along exercise: Correcting a flawed ternary relationship using aggregation.\"],\"engagement\":[\"Problem-solving challenge: \'Why can\'t we just use a regular relationship here?\' group discussion.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(11, 1, 3, NULL, 11, 'Reduction of ER Diagrams to Relational Tables - Part 1', 60, '[\"Apply mapping rules to convert strong and weak entity sets into relational tables.\",\"Map 1:1 and 1:N relationships to tables using foreign keys.\"]', 'Step-by-step algorithmic mapping walkthrough', '[\"Convert a basic Company ER diagram into a set of SQL-ready table schemas.\"]', '[\"Individual mapping exercise evaluated via real-time board work.\"]', '[\"Pairs match: Match ER components to their corresponding relational schema representation.\"]', NULL, '{\"session_number\":11,\"unit_id\":null,\"title\":\"Reduction of ER Diagrams to Relational Tables - Part 1\",\"duration_mins\":60,\"objectives\":[\"Apply mapping rules to convert strong and weak entity sets into relational tables.\",\"Map 1:1 and 1:N relationships to tables using foreign keys.\"],\"teaching_method\":\"Step-by-step algorithmic mapping walkthrough\",\"activities\":[\"Convert a basic Company ER diagram into a set of SQL-ready table schemas.\"],\"formative_assessment\":[\"Individual mapping exercise evaluated via real-time board work.\"],\"engagement\":[\"Pairs match: Match ER components to their corresponding relational schema representation.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(12, 1, 3, NULL, 12, 'Reduction of EER Diagrams to Relational Tables - Part 2', 60, '[\"Map N:M relationships and multi-valued attributes to relational tables.\",\"Convert EER specialization hierarchies (disjoint\\/overlapping) into tables.\"]', 'Problem-solving workshop', '[\"Transform a complex EER diagram with a specialization hierarchy into a relational schema.\"]', '[\"Class test on ER-to-Relational mapping rules.\"]', '[\"Design review: Critique a poorly mapped schema and point out redundant tables.\"]', NULL, '{\"session_number\":12,\"unit_id\":null,\"title\":\"Reduction of EER Diagrams to Relational Tables - Part 2\",\"duration_mins\":60,\"objectives\":[\"Map N:M relationships and multi-valued attributes to relational tables.\",\"Convert EER specialization hierarchies (disjoint\\/overlapping) into tables.\"],\"teaching_method\":\"Problem-solving workshop\",\"activities\":[\"Transform a complex EER diagram with a specialization hierarchy into a relational schema.\"],\"formative_assessment\":[\"Class test on ER-to-Relational mapping rules.\"],\"engagement\":[\"Design review: Critique a poorly mapped schema and point out redundant tables.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(13, 1, 3, NULL, 13, 'Structure of Relational Databases & Domain Constraints', 60, '[\"Define the mathematical concepts of relations, tuples, attributes, and domains.\",\"Explain domain constraints and their role in maintaining data integrity.\"]', 'Mathematical lecture and interactive definitions', '[\"Define domains for attributes of an online store (e.g., email, price, quantity).\"]', '[\"Short quiz on identifying valid and invalid tuple values based on domain constraints.\"]', '[\"Think-Pair-Share: How do domain constraints prevent bad data entry in web forms?\"]', NULL, '{\"session_number\":13,\"unit_id\":null,\"title\":\"Structure of Relational Databases & Domain Constraints\",\"duration_mins\":60,\"objectives\":[\"Define the mathematical concepts of relations, tuples, attributes, and domains.\",\"Explain domain constraints and their role in maintaining data integrity.\"],\"teaching_method\":\"Mathematical lecture and interactive definitions\",\"activities\":[\"Define domains for attributes of an online store (e.g., email, price, quantity).\"],\"formative_assessment\":[\"Short quiz on identifying valid and invalid tuple values based on domain constraints.\"],\"engagement\":[\"Think-Pair-Share: How do domain constraints prevent bad data entry in web forms?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(14, 1, 3, NULL, 14, 'Entity Integrity and Referential Integrity Constraints', 60, '[\"Explain the Entity Integrity constraint regarding primary keys.\",\"Formulate and enforce Referential Integrity constraints using foreign keys.\"]', 'Visual tracing of constraint violations', '[\"Analyze a set of tables to spot violations of entity and referential integrity.\"]', '[\"Solve a worksheet on ON DELETE CASCADE and ON DELETE SET NULL behaviors.\"]', '[\"Interactive simulation: Students act as database engines blocking invalid insert\\/delete operations.\"]', NULL, '{\"session_number\":14,\"unit_id\":null,\"title\":\"Entity Integrity and Referential Integrity Constraints\",\"duration_mins\":60,\"objectives\":[\"Explain the Entity Integrity constraint regarding primary keys.\",\"Formulate and enforce Referential Integrity constraints using foreign keys.\"],\"teaching_method\":\"Visual tracing of constraint violations\",\"activities\":[\"Analyze a set of tables to spot violations of entity and referential integrity.\"],\"formative_assessment\":[\"Solve a worksheet on ON DELETE CASCADE and ON DELETE SET NULL behaviors.\"],\"engagement\":[\"Interactive simulation: Students act as database engines blocking invalid insert\\/delete operations.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(15, 1, 3, NULL, 15, 'Relational Algebra: Select, Project, and Rename Operations', 60, '[\"Write formal relational algebra expressions using Select (\\u03c3) and Project (\\u03c0).\",\"Apply Rename (\\u03c1) operations to resolve relation name conflicts.\"]', 'Problem-solving session with step-by-step syntax building', '[\"Write expressions to retrieve specific rows and columns from a \'Library\' database.\"]', '[\"Board-work challenge: Write the relational algebra expression for a given natural language query.\"]', '[\"Speed challenge: Who can write the shortest correct expression for a query?\"]', NULL, '{\"session_number\":15,\"unit_id\":null,\"title\":\"Relational Algebra: Select, Project, and Rename Operations\",\"duration_mins\":60,\"objectives\":[\"Write formal relational algebra expressions using Select (\\u03c3) and Project (\\u03c0).\",\"Apply Rename (\\u03c1) operations to resolve relation name conflicts.\"],\"teaching_method\":\"Problem-solving session with step-by-step syntax building\",\"activities\":[\"Write expressions to retrieve specific rows and columns from a \'Library\' database.\"],\"formative_assessment\":[\"Board-work challenge: Write the relational algebra expression for a given natural language query.\"],\"engagement\":[\"Speed challenge: Who can write the shortest correct expression for a query?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(16, 1, 3, NULL, 16, 'Relational Algebra: Set Operations', 60, '[\"Apply Union (\\u222a), Set Difference (\\u2212), and Cartesian Product (\\u00d7) in relational algebra.\",\"Explain the prerequisite of union compatibility.\"]', 'Mathematical proof and set-theory visualization', '[\"Perform set operations on small sample tables of \'Online Customers\' and \'In-Store Customers\'.\"]', '[\"Identify why two given schemas cannot be combined using the Union operator.\"]', '[\"Venn diagram visualization: Draw set operations before writing the relational algebra.\"]', NULL, '{\"session_number\":16,\"unit_id\":null,\"title\":\"Relational Algebra: Set Operations\",\"duration_mins\":60,\"objectives\":[\"Apply Union (\\u222a), Set Difference (\\u2212), and Cartesian Product (\\u00d7) in relational algebra.\",\"Explain the prerequisite of union compatibility.\"],\"teaching_method\":\"Mathematical proof and set-theory visualization\",\"activities\":[\"Perform set operations on small sample tables of \'Online Customers\' and \'In-Store Customers\'.\"],\"formative_assessment\":[\"Identify why two given schemas cannot be combined using the Union operator.\"],\"engagement\":[\"Venn diagram visualization: Draw set operations before writing the relational algebra.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(17, 1, 3, NULL, 17, 'Relational Algebra: Join Operations', 60, '[\"Differentiate between Theta Join, Equijoin, and Natural Join.\",\"Formulate complex queries using join operations.\"]', 'Visual tracing of join execution paths', '[\"Manually compute the natural join of two relations with shared attributes.\"]', '[\"Solve a set of 3 join-computation problems.\"]', '[\"Interactive puzzle: Match join conditions to their resulting output tables.\"]', NULL, '{\"session_number\":17,\"unit_id\":null,\"title\":\"Relational Algebra: Join Operations\",\"duration_mins\":60,\"objectives\":[\"Differentiate between Theta Join, Equijoin, and Natural Join.\",\"Formulate complex queries using join operations.\"],\"teaching_method\":\"Visual tracing of join execution paths\",\"activities\":[\"Manually compute the natural join of two relations with shared attributes.\"],\"formative_assessment\":[\"Solve a set of 3 join-computation problems.\"],\"engagement\":[\"Interactive puzzle: Match join conditions to their resulting output tables.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(18, 1, 3, NULL, 18, 'Relational Algebra: Division and Intersection', 60, '[\"Explain the semantics of the Division (\\u00f7) operator.\",\"Formulate queries for \'all\' or \'every\' requirements using division.\"]', 'Problem-driven learning with step-by-step breakdown', '[\"Solve the classic \'find students who have taken all courses in CS\' query using division.\"]', '[\"Decompose a division operation into fundamental operators (Select, Project, Cartesian Product).\"]', '[\"Group challenge: Translate a complex natural language query into a division expression.\"]', NULL, '{\"session_number\":18,\"unit_id\":null,\"title\":\"Relational Algebra: Division and Intersection\",\"duration_mins\":60,\"objectives\":[\"Explain the semantics of the Division (\\u00f7) operator.\",\"Formulate queries for \'all\' or \'every\' requirements using division.\"],\"teaching_method\":\"Problem-driven learning with step-by-step breakdown\",\"activities\":[\"Solve the classic \'find students who have taken all courses in CS\' query using division.\"],\"formative_assessment\":[\"Decompose a division operation into fundamental operators (Select, Project, Cartesian Product).\"],\"engagement\":[\"Group challenge: Translate a complex natural language query into a division expression.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(19, 1, 3, NULL, 19, 'SQL DDL and Basic DML Commands', 60, '[\"Write SQL DDL commands to create, alter, and drop tables.\",\"Write basic DML commands (INSERT, UPDATE, DELETE) to manipulate data.\"]', 'Hands-on laboratory session and live-coding', '[\"Write and execute SQL scripts to build a schema for a student enrollment system.\"]', '[\"Live execution check of DDL scripts in PostgreSQL.\"]', '[\"Code-along: Build a database schema step-by-step with the instructor.\"]', NULL, '{\"session_number\":19,\"unit_id\":null,\"title\":\"SQL DDL and Basic DML Commands\",\"duration_mins\":60,\"objectives\":[\"Write SQL DDL commands to create, alter, and drop tables.\",\"Write basic DML commands (INSERT, UPDATE, DELETE) to manipulate data.\"],\"teaching_method\":\"Hands-on laboratory session and live-coding\",\"activities\":[\"Write and execute SQL scripts to build a schema for a student enrollment system.\"],\"formative_assessment\":[\"Live execution check of DDL scripts in PostgreSQL.\"],\"engagement\":[\"Code-along: Build a database schema step-by-step with the instructor.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(20, 1, 3, NULL, 20, 'Enforcing Integrity Constraints in SQL', 60, '[\"Implement NOT NULL, UNIQUE, PRIMARY KEY, and FOREIGN KEY constraints in SQL.\",\"Apply CHECK and DEFAULT constraints to enforce business rules.\"]', 'Hands-on laboratory session', '[\"Modify existing tables to add constraints and test them by attempting to insert invalid data.\"]', '[\"Verify constraint enforcement by writing queries that trigger constraint violations.\"]', '[\"Break-the-database: Students try to insert \'illegal\' records into a classmate\'s database.\"]', NULL, '{\"session_number\":20,\"unit_id\":null,\"title\":\"Enforcing Integrity Constraints in SQL\",\"duration_mins\":60,\"objectives\":[\"Implement NOT NULL, UNIQUE, PRIMARY KEY, and FOREIGN KEY constraints in SQL.\",\"Apply CHECK and DEFAULT constraints to enforce business rules.\"],\"teaching_method\":\"Hands-on laboratory session\",\"activities\":[\"Modify existing tables to add constraints and test them by attempting to insert invalid data.\"],\"formative_assessment\":[\"Verify constraint enforcement by writing queries that trigger constraint violations.\"],\"engagement\":[\"Break-the-database: Students try to insert \'illegal\' records into a classmate\'s database.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(21, 1, 3, NULL, 21, 'SQL Aggregation and Grouping', 60, '[\"Apply aggregate functions (SUM, AVG, COUNT, MIN, MAX) in SQL.\",\"Formulate queries using GROUP BY and HAVING clauses.\"]', 'Interactive live-coding and query optimization', '[\"Write queries to find the average salary of employees in each department with more than 5 employees.\"]', '[\"Differentiate between WHERE and HAVING clauses in a quick quiz.\"]', '[\"Query debugging: Find the syntax error in a given GROUP BY query.\"]', NULL, '{\"session_number\":21,\"unit_id\":null,\"title\":\"SQL Aggregation and Grouping\",\"duration_mins\":60,\"objectives\":[\"Apply aggregate functions (SUM, AVG, COUNT, MIN, MAX) in SQL.\",\"Formulate queries using GROUP BY and HAVING clauses.\"],\"teaching_method\":\"Interactive live-coding and query optimization\",\"activities\":[\"Write queries to find the average salary of employees in each department with more than 5 employees.\"],\"formative_assessment\":[\"Differentiate between WHERE and HAVING clauses in a quick quiz.\"],\"engagement\":[\"Query debugging: Find the syntax error in a given GROUP BY query.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(22, 1, 3, NULL, 22, 'Nested Subqueries and Set Operations in SQL', 60, '[\"Write nested subqueries using IN, EXISTS, UNIQUE, and ALL\\/ANY comparison operators.\",\"Combine query results using UNION, INTERSECT, and EXCEPT.\"]', 'Hands-on laboratory session', '[\"Implement complex nested queries to solve multi-level data retrieval problems.\"]', '[\"Online coding challenge on nested subqueries.\"]', '[\"Peer code review: Compare nested subquery solutions with equivalent JOIN solutions.\"]', NULL, '{\"session_number\":22,\"unit_id\":null,\"title\":\"Nested Subqueries and Set Operations in SQL\",\"duration_mins\":60,\"objectives\":[\"Write nested subqueries using IN, EXISTS, UNIQUE, and ALL\\/ANY comparison operators.\",\"Combine query results using UNION, INTERSECT, and EXCEPT.\"],\"teaching_method\":\"Hands-on laboratory session\",\"activities\":[\"Implement complex nested queries to solve multi-level data retrieval problems.\"],\"formative_assessment\":[\"Online coding challenge on nested subqueries.\"],\"engagement\":[\"Peer code review: Compare nested subquery solutions with equivalent JOIN solutions.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(23, 1, 3, NULL, 23, 'Creating and Managing Views in SQL', 60, '[\"Create, update, and drop views in SQL.\",\"Explain the security and abstraction benefits of views.\"]', 'Interactive live-coding demonstration', '[\"Create a restricted view of employee data for HR assistants and test access permissions.\"]', '[\"Identify whether a given view is updatable based on SQL standards.\"]', '[\"Discussion: How do views act as a security layer in enterprise databases?\"]', NULL, '{\"session_number\":23,\"unit_id\":null,\"title\":\"Creating and Managing Views in SQL\",\"duration_mins\":60,\"objectives\":[\"Create, update, and drop views in SQL.\",\"Explain the security and abstraction benefits of views.\"],\"teaching_method\":\"Interactive live-coding demonstration\",\"activities\":[\"Create a restricted view of employee data for HR assistants and test access permissions.\"],\"formative_assessment\":[\"Identify whether a given view is updatable based on SQL standards.\"],\"engagement\":[\"Discussion: How do views act as a security layer in enterprise databases?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(24, 1, 3, NULL, 24, 'Introduction to SQL Index Structures', 60, '[\"Create and drop indexes in SQL.\",\"Explain how indexes speed up query retrieval at the cost of update performance.\"]', 'Hands-on laboratory session and performance analysis', '[\"Measure query execution time before and after creating an index on a large dataset.\"]', '[\"Laboratory practical exam on SQL queries, Joins, and Index creation.\"]', '[\"Performance race: Compare query execution plans using EXPLAIN in PostgreSQL.\"]', NULL, '{\"session_number\":24,\"unit_id\":null,\"title\":\"Introduction to SQL Index Structures\",\"duration_mins\":60,\"objectives\":[\"Create and drop indexes in SQL.\",\"Explain how indexes speed up query retrieval at the cost of update performance.\"],\"teaching_method\":\"Hands-on laboratory session and performance analysis\",\"activities\":[\"Measure query execution time before and after creating an index on a large dataset.\"],\"formative_assessment\":[\"Laboratory practical exam on SQL queries, Joins, and Index creation.\"],\"engagement\":[\"Performance race: Compare query execution plans using EXPLAIN in PostgreSQL.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(25, 1, 3, NULL, 25, 'Pitfalls in Relational Design & Data Redundancy', 60, '[\"Identify data redundancy in poorly designed relational schemas.\",\"Explain the consequences of uncontrolled redundancy on storage and performance.\"]', 'Problem-driven learning using messy spreadsheets', '[\"Analyze a single-table university spreadsheet containing student, course, and instructor details.\"]', '[\"List three distinct redundant data points in the provided spreadsheet.\"]', '[\"Think-Pair-Share: How does redundancy lead to inconsistent data over time?\"]', NULL, '{\"session_number\":25,\"unit_id\":null,\"title\":\"Pitfalls in Relational Design & Data Redundancy\",\"duration_mins\":60,\"objectives\":[\"Identify data redundancy in poorly designed relational schemas.\",\"Explain the consequences of uncontrolled redundancy on storage and performance.\"],\"teaching_method\":\"Problem-driven learning using messy spreadsheets\",\"activities\":[\"Analyze a single-table university spreadsheet containing student, course, and instructor details.\"],\"formative_assessment\":[\"List three distinct redundant data points in the provided spreadsheet.\"],\"engagement\":[\"Think-Pair-Share: How does redundancy lead to inconsistent data over time?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(26, 1, 3, NULL, 26, 'Database Anomalies: Insertion, Deletion, and Update', 60, '[\"Define and demonstrate insertion anomalies.\",\"Define and demonstrate deletion and update anomalies.\"]', 'Interactive scenario-based analysis', '[\"Perform mock database updates on a flat table to trigger anomalies.\"]', '[\"Identify the type of anomaly occurring in a set of database operation scenarios.\"]', '[\"Role-play: Students act as data entry operators dealing with the frustration of anomalies.\"]', NULL, '{\"session_number\":26,\"unit_id\":null,\"title\":\"Database Anomalies: Insertion, Deletion, and Update\",\"duration_mins\":60,\"objectives\":[\"Define and demonstrate insertion anomalies.\",\"Define and demonstrate deletion and update anomalies.\"],\"teaching_method\":\"Interactive scenario-based analysis\",\"activities\":[\"Perform mock database updates on a flat table to trigger anomalies.\"],\"formative_assessment\":[\"Identify the type of anomaly occurring in a set of database operation scenarios.\"],\"engagement\":[\"Role-play: Students act as data entry operators dealing with the frustration of anomalies.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(27, 1, 3, NULL, 27, 'Functional Dependencies: Definition and Types', 60, '[\"Define functional dependencies (FDs) mathematically.\",\"Distinguish between trivial and non-trivial functional dependencies.\"]', 'Mathematical lecture and proof walkthroughs', '[\"Identify valid functional dependencies from a given instance of a relation.\"]', '[\"Short quiz on verifying FDs on a sample relation instance.\"]', '[\"Concept mapping: Relate functional dependencies to mathematical functions (y = f(x)).\"]', NULL, '{\"session_number\":27,\"unit_id\":null,\"title\":\"Functional Dependencies: Definition and Types\",\"duration_mins\":60,\"objectives\":[\"Define functional dependencies (FDs) mathematically.\",\"Distinguish between trivial and non-trivial functional dependencies.\"],\"teaching_method\":\"Mathematical lecture and proof walkthroughs\",\"activities\":[\"Identify valid functional dependencies from a given instance of a relation.\"],\"formative_assessment\":[\"Short quiz on verifying FDs on a sample relation instance.\"],\"engagement\":[\"Concept mapping: Relate functional dependencies to mathematical functions (y = f(x)).\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(28, 1, 3, NULL, 28, 'Armstrong\'s Axioms & Closure of Functional Dependencies', 60, '[\"Apply Armstrong\'s Axioms (reflexivity, augmentation, transitivity) to derive FDs.\",\"Compute the attribute closure (X+) for a set of attributes.\"]', 'Step-by-step problem-solving session', '[\"Compute candidate keys of a relation using attribute closure algorithms.\"]', '[\"Solve a set of 3 attribute closure problems on the board.\"]', '[\"Speed-run: Find all candidate keys for a given schema and FD set in under 3 minutes.\"]', NULL, '{\"session_number\":28,\"unit_id\":null,\"title\":\"Armstrong\'s Axioms & Closure of Functional Dependencies\",\"duration_mins\":60,\"objectives\":[\"Apply Armstrong\'s Axioms (reflexivity, augmentation, transitivity) to derive FDs.\",\"Compute the attribute closure (X+) for a set of attributes.\"],\"teaching_method\":\"Step-by-step problem-solving session\",\"activities\":[\"Compute candidate keys of a relation using attribute closure algorithms.\"],\"formative_assessment\":[\"Solve a set of 3 attribute closure problems on the board.\"],\"engagement\":[\"Speed-run: Find all candidate keys for a given schema and FD set in under 3 minutes.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(29, 1, 3, NULL, 29, 'Canonical Cover of Functional Dependencies', 60, '[\"Define extraneous attributes in a set of functional dependencies.\",\"Compute the canonical cover (Fc) for a given set of FDs.\"]', 'Algorithmic dry-runs and peer instruction', '[\"Apply the canonical cover algorithm step-by-step to simplify a complex set of FDs.\"]', '[\"Home assignment on finding Canonical Cover and testing Lossless-Join property.\"]', '[\"Peer review: Swap simplified FD sets and check for missing or extra dependencies.\"]', NULL, '{\"session_number\":29,\"unit_id\":null,\"title\":\"Canonical Cover of Functional Dependencies\",\"duration_mins\":60,\"objectives\":[\"Define extraneous attributes in a set of functional dependencies.\",\"Compute the canonical cover (Fc) for a given set of FDs.\"],\"teaching_method\":\"Algorithmic dry-runs and peer instruction\",\"activities\":[\"Apply the canonical cover algorithm step-by-step to simplify a complex set of FDs.\"],\"formative_assessment\":[\"Home assignment on finding Canonical Cover and testing Lossless-Join property.\"],\"engagement\":[\"Peer review: Swap simplified FD sets and check for missing or extra dependencies.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(30, 1, 3, NULL, 30, 'First Normal Form (1NF) and Second Normal Form (2NF)', 60, '[\"Define atomic domains and normalize a relation to 1NF.\",\"Identify partial functional dependencies and normalize a relation to 2NF.\"]', 'Problem-driven learning with step-by-step normalization', '[\"Decompose a non-1NF table containing multi-valued attributes into 2NF tables.\"]', '[\"Identify partial dependencies in a given schema and propose a 2NF decomposition.\"]', '[\"Interactive poll: Is a composite attribute a violation of 1NF?\"]', NULL, '{\"session_number\":30,\"unit_id\":null,\"title\":\"First Normal Form (1NF) and Second Normal Form (2NF)\",\"duration_mins\":60,\"objectives\":[\"Define atomic domains and normalize a relation to 1NF.\",\"Identify partial functional dependencies and normalize a relation to 2NF.\"],\"teaching_method\":\"Problem-driven learning with step-by-step normalization\",\"activities\":[\"Decompose a non-1NF table containing multi-valued attributes into 2NF tables.\"],\"formative_assessment\":[\"Identify partial dependencies in a given schema and propose a 2NF decomposition.\"],\"engagement\":[\"Interactive poll: Is a composite attribute a violation of 1NF?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(31, 1, 3, NULL, 31, 'Third Normal Form (3NF)', 60, '[\"Define transitive functional dependencies.\",\"Normalize a relation to 3NF using the formal definition.\"]', 'Step-by-step problem solving', '[\"Decompose a 2NF schema with transitive dependencies into 3NF.\"]', '[\"Check if a given schema is in 3NF by analyzing its FDs.\"]', '[\"Critique session: Analyze a real-world schema and identify transitive dependencies.\"]', NULL, '{\"session_number\":31,\"unit_id\":null,\"title\":\"Third Normal Form (3NF)\",\"duration_mins\":60,\"objectives\":[\"Define transitive functional dependencies.\",\"Normalize a relation to 3NF using the formal definition.\"],\"teaching_method\":\"Step-by-step problem solving\",\"activities\":[\"Decompose a 2NF schema with transitive dependencies into 3NF.\"],\"formative_assessment\":[\"Check if a given schema is in 3NF by analyzing its FDs.\"],\"engagement\":[\"Critique session: Analyze a real-world schema and identify transitive dependencies.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(32, 1, 3, NULL, 32, 'Boyce-Codd Normal Form (BCNF)', 60, '[\"Compare 3NF and BCNF definitions.\",\"Decompose a relation into BCNF when non-trivial FDs violate the superkey condition.\"]', 'Comparative analysis and problem-solving', '[\"Analyze a classic scenario (Student, Advisor, Subject) that is in 3NF but not BCNF.\"]', '[\"Solve a BCNF decomposition problem on a given schema.\"]', '[\"Debate: Is BCNF always better than 3NF? Discuss the trade-offs.\"]', NULL, '{\"session_number\":32,\"unit_id\":null,\"title\":\"Boyce-Codd Normal Form (BCNF)\",\"duration_mins\":60,\"objectives\":[\"Compare 3NF and BCNF definitions.\",\"Decompose a relation into BCNF when non-trivial FDs violate the superkey condition.\"],\"teaching_method\":\"Comparative analysis and problem-solving\",\"activities\":[\"Analyze a classic scenario (Student, Advisor, Subject) that is in 3NF but not BCNF.\"],\"formative_assessment\":[\"Solve a BCNF decomposition problem on a given schema.\"],\"engagement\":[\"Debate: Is BCNF always better than 3NF? Discuss the trade-offs.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(33, 1, 3, NULL, 33, 'Fourth Normal Form (4NF) & Multivalued Dependencies', 60, '[\"Define multivalued dependencies (MVDs).\",\"Normalize a relation to 4NF by eliminating MVDs.\"]', 'Visual modeling and problem-solving', '[\"Identify MVDs in a table containing (Restaurant, Delivery Area, Cuisine Type) and decompose it.\"]', '[\"Explain the difference between a functional dependency and a multivalued dependency.\"]', '[\"Think-Pair-Share: How do independent multi-valued attributes cause redundancy in a single table?\"]', NULL, '{\"session_number\":33,\"unit_id\":null,\"title\":\"Fourth Normal Form (4NF) & Multivalued Dependencies\",\"duration_mins\":60,\"objectives\":[\"Define multivalued dependencies (MVDs).\",\"Normalize a relation to 4NF by eliminating MVDs.\"],\"teaching_method\":\"Visual modeling and problem-solving\",\"activities\":[\"Identify MVDs in a table containing (Restaurant, Delivery Area, Cuisine Type) and decompose it.\"],\"formative_assessment\":[\"Explain the difference between a functional dependency and a multivalued dependency.\"],\"engagement\":[\"Think-Pair-Share: How do independent multi-valued attributes cause redundancy in a single table?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(34, 1, 3, NULL, 34, 'Fifth Normal Form (5NF) & Join Dependencies', 60, '[\"Define join dependencies and Fifth Normal Form (5NF).\",\"Explain scenarios where a relation cannot be reconstructed without 5NF.\"]', 'Theoretical lecture with complex visual examples', '[\"Analyze a 3-way relationship that requires 5NF decomposition to avoid join anomalies.\"]', '[\"Identify join dependencies in a given scenario.\"]', '[\"Group discussion: The practical rarity of 5NF in real-world database design.\"]', NULL, '{\"session_number\":34,\"unit_id\":null,\"title\":\"Fifth Normal Form (5NF) & Join Dependencies\",\"duration_mins\":60,\"objectives\":[\"Define join dependencies and Fifth Normal Form (5NF).\",\"Explain scenarios where a relation cannot be reconstructed without 5NF.\"],\"teaching_method\":\"Theoretical lecture with complex visual examples\",\"activities\":[\"Analyze a 3-way relationship that requires 5NF decomposition to avoid join anomalies.\"],\"formative_assessment\":[\"Identify join dependencies in a given scenario.\"],\"engagement\":[\"Group discussion: The practical rarity of 5NF in real-world database design.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(35, 1, 3, NULL, 35, 'Decomposition Properties: Lossless-Join Decomposition', 60, '[\"Explain the importance of lossless-join decomposition.\",\"Test a decomposition for the lossless-join property using the matrix method.\"]', 'Algorithmic verification and mathematical proof', '[\"Apply Chase algorithm (matrix method) to verify if a decomposition is lossless.\"]', '[\"Solve a lossless-join verification problem.\"]', '[\"Interactive simulation: Reconstruct a decomposed table to see if \'spurious tuples\' appear.\"]', NULL, '{\"session_number\":35,\"unit_id\":null,\"title\":\"Decomposition Properties: Lossless-Join Decomposition\",\"duration_mins\":60,\"objectives\":[\"Explain the importance of lossless-join decomposition.\",\"Test a decomposition for the lossless-join property using the matrix method.\"],\"teaching_method\":\"Algorithmic verification and mathematical proof\",\"activities\":[\"Apply Chase algorithm (matrix method) to verify if a decomposition is lossless.\"],\"formative_assessment\":[\"Solve a lossless-join verification problem.\"],\"engagement\":[\"Interactive simulation: Reconstruct a decomposed table to see if \'spurious tuples\' appear.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(36, 1, 3, NULL, 36, 'Decomposition Properties: Dependency Preservation', 60, '[\"Define the dependency preservation property.\",\"Test whether a decomposition preserves all functional dependencies.\"]', 'Problem-solving workshop and peer instruction', '[\"Compute the projection of FDs on decomposed relations to verify dependency preservation.\"]', '[\"Mid-Semester Examination containing analytical normalization and decomposition problems.\"]', '[\"Design review: Critique a decomposition that is lossless but fails dependency preservation.\"]', NULL, '{\"session_number\":36,\"unit_id\":null,\"title\":\"Decomposition Properties: Dependency Preservation\",\"duration_mins\":60,\"objectives\":[\"Define the dependency preservation property.\",\"Test whether a decomposition preserves all functional dependencies.\"],\"teaching_method\":\"Problem-solving workshop and peer instruction\",\"activities\":[\"Compute the projection of FDs on decomposed relations to verify dependency preservation.\"],\"formative_assessment\":[\"Mid-Semester Examination containing analytical normalization and decomposition problems.\"],\"engagement\":[\"Design review: Critique a decomposition that is lossless but fails dependency preservation.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(37, 1, 3, NULL, 37, 'Transaction Concepts and States', 60, '[\"Define a transaction in a database context.\",\"Trace a transaction through its lifecycle states (Active, Partially Committed, Committed, Failed, Aborted).\"]', 'State-machine tracing and interactive lecture', '[\"Draw and label the transaction state transition diagram.\"]', '[\"Identify the state of a transaction given a sequence of database events.\"]', '[\"Analogy: Map transaction states to a real-life online shopping checkout process.\"]', NULL, '{\"session_number\":37,\"unit_id\":null,\"title\":\"Transaction Concepts and States\",\"duration_mins\":60,\"objectives\":[\"Define a transaction in a database context.\",\"Trace a transaction through its lifecycle states (Active, Partially Committed, Committed, Failed, Aborted).\"],\"teaching_method\":\"State-machine tracing and interactive lecture\",\"activities\":[\"Draw and label the transaction state transition diagram.\"],\"formative_assessment\":[\"Identify the state of a transaction given a sequence of database events.\"],\"engagement\":[\"Analogy: Map transaction states to a real-life online shopping checkout process.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(38, 1, 3, NULL, 38, 'ACID Properties of Transactions', 60, '[\"Explain Atomicity, Consistency, Isolation, and Durability (ACID).\",\"Identify which DBMS component is responsible for enforcing each ACID property.\"]', 'Case-study analysis of financial transaction failures', '[\"Analyze scenarios where a system crash violates Atomicity or Durability.\"]', '[\"Match-the-following: ACID properties vs. DBMS components (e.g., Recovery Manager, Concurrency Control).\"]', '[\"Debate: Which of the ACID properties is the hardest to guarantee in a distributed system?\"]', NULL, '{\"session_number\":38,\"unit_id\":null,\"title\":\"ACID Properties of Transactions\",\"duration_mins\":60,\"objectives\":[\"Explain Atomicity, Consistency, Isolation, and Durability (ACID).\",\"Identify which DBMS component is responsible for enforcing each ACID property.\"],\"teaching_method\":\"Case-study analysis of financial transaction failures\",\"activities\":[\"Analyze scenarios where a system crash violates Atomicity or Durability.\"],\"formative_assessment\":[\"Match-the-following: ACID properties vs. DBMS components (e.g., Recovery Manager, Concurrency Control).\"],\"engagement\":[\"Debate: Which of the ACID properties is the hardest to guarantee in a distributed system?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42');
INSERT INTO `lesson_plans` (`id`, `plan_id`, `professor_id`, `unit_id`, `session_number`, `title`, `duration_mins`, `objectives`, `teaching_method`, `activities`, `formative_assessment`, `engagement`, `materials`, `content`, `session_date`, `session_status`, `planned_date`, `actual_date`, `bloom_k_level`, `unit_number`, `suggested_method`, `resources`, `calendar_event_id`, `period_label`, `meta`, `created_at`) VALUES
(39, 1, 3, NULL, 39, 'Schedules and Concurrent Executions', 60, '[\"Define serial and concurrent schedules.\",\"Identify read-write conflicts in concurrent executions.\"]', 'Visual tracing of concurrent execution timelines', '[\"Write down the step-by-step execution sequence of two concurrent transactions.\"]', '[\"Identify conflicting operations in a given schedule.\"]', '[\"Role-play: Two students act as concurrent transactions trying to update the same bank balance.\"]', NULL, '{\"session_number\":39,\"unit_id\":null,\"title\":\"Schedules and Concurrent Executions\",\"duration_mins\":60,\"objectives\":[\"Define serial and concurrent schedules.\",\"Identify read-write conflicts in concurrent executions.\"],\"teaching_method\":\"Visual tracing of concurrent execution timelines\",\"activities\":[\"Write down the step-by-step execution sequence of two concurrent transactions.\"],\"formative_assessment\":[\"Identify conflicting operations in a given schedule.\"],\"engagement\":[\"Role-play: Two students act as concurrent transactions trying to update the same bank balance.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(40, 1, 3, NULL, 40, 'Conflict Serializability', 60, '[\"Define conflict equivalence and conflict serializability.\",\"Construct a precedence graph to test a schedule for conflict serializability.\"]', 'Algorithmic graph drawing and problem solving', '[\"Draw precedence graphs for three different schedules and determine if they are conflict serializable.\"]', '[\"Quiz on identifying conflict serializable schedules using precedence graphs.\"]', '[\"Speed-drawing: Students race to find cycles in complex precedence graphs.\"]', NULL, '{\"session_number\":40,\"unit_id\":null,\"title\":\"Conflict Serializability\",\"duration_mins\":60,\"objectives\":[\"Define conflict equivalence and conflict serializability.\",\"Construct a precedence graph to test a schedule for conflict serializability.\"],\"teaching_method\":\"Algorithmic graph drawing and problem solving\",\"activities\":[\"Draw precedence graphs for three different schedules and determine if they are conflict serializable.\"],\"formative_assessment\":[\"Quiz on identifying conflict serializable schedules using precedence graphs.\"],\"engagement\":[\"Speed-drawing: Students race to find cycles in complex precedence graphs.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(41, 1, 3, NULL, 41, 'View Serializability', 60, '[\"Define view equivalence and view serializability.\",\"Explain the relationship between conflict serializability and view serializability.\"]', 'Comparative analysis and logical proofs', '[\"Analyze a schedule that is view serializable but not conflict serializable.\"]', '[\"Determine if a given schedule is view serializable using the labeled precedence graph method.\"]', '[\"Think-Pair-Share: Why is view serializability rarely used in practice compared to conflict serializability?\"]', NULL, '{\"session_number\":41,\"unit_id\":null,\"title\":\"View Serializability\",\"duration_mins\":60,\"objectives\":[\"Define view equivalence and view serializability.\",\"Explain the relationship between conflict serializability and view serializability.\"],\"teaching_method\":\"Comparative analysis and logical proofs\",\"activities\":[\"Analyze a schedule that is view serializable but not conflict serializable.\"],\"formative_assessment\":[\"Determine if a given schedule is view serializable using the labeled precedence graph method.\"],\"engagement\":[\"Think-Pair-Share: Why is view serializability rarely used in practice compared to conflict serializability?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(42, 1, 3, NULL, 42, 'Recoverability: Cascadeless and Recoverable Schedules', 60, '[\"Define recoverable and cascadeless schedules.\",\"Identify cascading rollbacks in concurrent schedules.\"]', 'Visual tracing of rollback scenarios', '[\"Trace a schedule where aborting one transaction forces multiple other transactions to roll back.\"]', '[\"Classify a given schedule as recoverable, cascadeless, or non-recoverable.\"]', '[\"Interactive simulation: Trace the domino effect of a cascading rollback on a whiteboard.\"]', NULL, '{\"session_number\":42,\"unit_id\":null,\"title\":\"Recoverability: Cascadeless and Recoverable Schedules\",\"duration_mins\":60,\"objectives\":[\"Define recoverable and cascadeless schedules.\",\"Identify cascading rollbacks in concurrent schedules.\"],\"teaching_method\":\"Visual tracing of rollback scenarios\",\"activities\":[\"Trace a schedule where aborting one transaction forces multiple other transactions to roll back.\"],\"formative_assessment\":[\"Classify a given schedule as recoverable, cascadeless, or non-recoverable.\"],\"engagement\":[\"Interactive simulation: Trace the domino effect of a cascading rollback on a whiteboard.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(43, 1, 3, NULL, 43, 'Lock-Based Protocols: Shared and Exclusive Locks', 60, '[\"Explain the lock compatibility matrix.\",\"Apply basic locking (Shared\\/Exclusive) to concurrent transactions.\"]', 'Interactive simulation tools for lock-based protocols', '[\"Trace a schedule with lock request and release operations, noting lock grants and waits.\"]', '[\"Determine if a lock request will be granted or queued based on the current lock table.\"]', '[\"Locking game: Students use colored cards (Red for Exclusive, Green for Shared) to request access to resources.\"]', NULL, '{\"session_number\":43,\"unit_id\":null,\"title\":\"Lock-Based Protocols: Shared and Exclusive Locks\",\"duration_mins\":60,\"objectives\":[\"Explain the lock compatibility matrix.\",\"Apply basic locking (Shared\\/Exclusive) to concurrent transactions.\"],\"teaching_method\":\"Interactive simulation tools for lock-based protocols\",\"activities\":[\"Trace a schedule with lock request and release operations, noting lock grants and waits.\"],\"formative_assessment\":[\"Determine if a lock request will be granted or queued based on the current lock table.\"],\"engagement\":[\"Locking game: Students use colored cards (Red for Exclusive, Green for Shared) to request access to resources.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(44, 1, 3, NULL, 44, 'Two-Phase Locking (2PL) Protocol', 60, '[\"Explain the growing and shrinking phases of the Two-Phase Locking (2PL) protocol.\",\"Prove that 2PL guarantees conflict serializability.\"]', 'Mathematical proof and visual tracing', '[\"Convert a non-2PL schedule into a 2PL-compliant schedule by inserting lock\\/unlock points.\"]', '[\"Identify violations of the 2PL protocol in a given transaction execution.\"]', '[\"Peer instruction: Explain to your partner why releasing a lock early prevents 2PL compliance.\"]', NULL, '{\"session_number\":44,\"unit_id\":null,\"title\":\"Two-Phase Locking (2PL) Protocol\",\"duration_mins\":60,\"objectives\":[\"Explain the growing and shrinking phases of the Two-Phase Locking (2PL) protocol.\",\"Prove that 2PL guarantees conflict serializability.\"],\"teaching_method\":\"Mathematical proof and visual tracing\",\"activities\":[\"Convert a non-2PL schedule into a 2PL-compliant schedule by inserting lock\\/unlock points.\"],\"formative_assessment\":[\"Identify violations of the 2PL protocol in a given transaction execution.\"],\"engagement\":[\"Peer instruction: Explain to your partner why releasing a lock early prevents 2PL compliance.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(45, 1, 3, NULL, 45, 'Strict 2PL and Rigorous 2PL', 60, '[\"Differentiate between Basic 2PL, Strict 2PL, and Rigorous 2PL.\",\"Explain how Strict 2PL guarantees cascadeless recovery.\"]', 'Comparative analysis and timeline tracing', '[\"Trace concurrent transactions under Strict 2PL and observe how cascading rollbacks are prevented.\"]', '[\"Solve a worksheet comparing the concurrency levels allowed by Basic, Strict, and Rigorous 2PL.\"]', '[\"Discussion: Why do modern commercial databases prefer Strict 2PL over Basic 2PL?\"]', NULL, '{\"session_number\":45,\"unit_id\":null,\"title\":\"Strict 2PL and Rigorous 2PL\",\"duration_mins\":60,\"objectives\":[\"Differentiate between Basic 2PL, Strict 2PL, and Rigorous 2PL.\",\"Explain how Strict 2PL guarantees cascadeless recovery.\"],\"teaching_method\":\"Comparative analysis and timeline tracing\",\"activities\":[\"Trace concurrent transactions under Strict 2PL and observe how cascading rollbacks are prevented.\"],\"formative_assessment\":[\"Solve a worksheet comparing the concurrency levels allowed by Basic, Strict, and Rigorous 2PL.\"],\"engagement\":[\"Discussion: Why do modern commercial databases prefer Strict 2PL over Basic 2PL?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(46, 1, 3, NULL, 46, 'Deadlocks in Databases: Prevention and Detection', 60, '[\"Explain how deadlocks occur in lock-based protocols.\",\"Apply deadlock prevention strategies (Wait-Die, Wound-Wait) and detection using Wait-For Graphs (WFG).\"]', 'Algorithmic dry-runs and graph analysis', '[\"Draw a Wait-For Graph for a deadlocked system and identify the victim transaction to abort.\"]', '[\"Analytical problem-solving test on Deadlock detection algorithms.\"]', '[\"Interactive simulation: Run a live deadlock scenario and let students decide which transaction to \'kill\' to resolve it.\"]', NULL, '{\"session_number\":46,\"unit_id\":null,\"title\":\"Deadlocks in Databases: Prevention and Detection\",\"duration_mins\":60,\"objectives\":[\"Explain how deadlocks occur in lock-based protocols.\",\"Apply deadlock prevention strategies (Wait-Die, Wound-Wait) and detection using Wait-For Graphs (WFG).\"],\"teaching_method\":\"Algorithmic dry-runs and graph analysis\",\"activities\":[\"Draw a Wait-For Graph for a deadlocked system and identify the victim transaction to abort.\"],\"formative_assessment\":[\"Analytical problem-solving test on Deadlock detection algorithms.\"],\"engagement\":[\"Interactive simulation: Run a live deadlock scenario and let students decide which transaction to \'kill\' to resolve it.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(47, 1, 3, NULL, 47, 'Timestamp-Based Protocols', 60, '[\"Explain the concept of transaction timestamps.\",\"Apply the Timestamp Ordering protocol to order read and write operations.\"]', 'Step-by-step algorithmic tracing', '[\"Trace a sequence of read\\/write requests using read-timestamps and write-timestamps on data items.\"]', '[\"Determine if a read or write operation will be rejected\\/rolled back under the Timestamp Ordering protocol.\"]', '[\"Timeline tracing: Use colored markers to track read\\/write timestamps on a shared timeline.\"]', NULL, '{\"session_number\":47,\"unit_id\":null,\"title\":\"Timestamp-Based Protocols\",\"duration_mins\":60,\"objectives\":[\"Explain the concept of transaction timestamps.\",\"Apply the Timestamp Ordering protocol to order read and write operations.\"],\"teaching_method\":\"Step-by-step algorithmic tracing\",\"activities\":[\"Trace a sequence of read\\/write requests using read-timestamps and write-timestamps on data items.\"],\"formative_assessment\":[\"Determine if a read or write operation will be rejected\\/rolled back under the Timestamp Ordering protocol.\"],\"engagement\":[\"Timeline tracing: Use colored markers to track read\\/write timestamps on a shared timeline.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(48, 1, 3, NULL, 48, 'Thomas\' Write Rule & Validation-Based Protocols', 60, '[\"Apply Thomas\' Write Rule to optimize timestamp-based concurrency control.\",\"Describe the phases of Validation-Based (Optimistic) concurrency control.\"]', 'Comparative analysis and algorithmic walkthroughs', '[\"Trace a schedule where Thomas\' Write Rule allows a write operation that would otherwise be rejected.\"]', '[\"Compare optimistic vs. pessimistic concurrency control in a short written summary.\"]', '[\"Debate: When should you use Validation-Based protocols instead of Lock-Based protocols?\"]', NULL, '{\"session_number\":48,\"unit_id\":null,\"title\":\"Thomas\' Write Rule & Validation-Based Protocols\",\"duration_mins\":60,\"objectives\":[\"Apply Thomas\' Write Rule to optimize timestamp-based concurrency control.\",\"Describe the phases of Validation-Based (Optimistic) concurrency control.\"],\"teaching_method\":\"Comparative analysis and algorithmic walkthroughs\",\"activities\":[\"Trace a schedule where Thomas\' Write Rule allows a write operation that would otherwise be rejected.\"],\"formative_assessment\":[\"Compare optimistic vs. pessimistic concurrency control in a short written summary.\"],\"engagement\":[\"Debate: When should you use Validation-Based protocols instead of Lock-Based protocols?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(49, 1, 3, NULL, 49, 'Storage Architecture: File and Record Organization', 60, '[\"Describe the physical storage hierarchy (magnetic disks, SSDs, main memory).\",\"Compare fixed-length and variable-length record organizations.\"]', 'Architectural diagrams and comparative analysis', '[\"Calculate the block utilization and span for a given record size and block size.\"]', '[\"Short quiz on calculating record offsets in a variable-length record block.\"]', '[\"Analogy: Comparing database blocks and records to shipping containers and boxes.\"]', NULL, '{\"session_number\":49,\"unit_id\":null,\"title\":\"Storage Architecture: File and Record Organization\",\"duration_mins\":60,\"objectives\":[\"Describe the physical storage hierarchy (magnetic disks, SSDs, main memory).\",\"Compare fixed-length and variable-length record organizations.\"],\"teaching_method\":\"Architectural diagrams and comparative analysis\",\"activities\":[\"Calculate the block utilization and span for a given record size and block size.\"],\"formative_assessment\":[\"Short quiz on calculating record offsets in a variable-length record block.\"],\"engagement\":[\"Analogy: Comparing database blocks and records to shipping containers and boxes.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(50, 1, 3, NULL, 50, 'File Organizations & RAID Levels', 60, '[\"Compare Heap, Sequential, and Hashing file organizations.\",\"Analyze RAID levels (RAID 0, 1, 5, 10) for performance and fault tolerance.\"]', 'Comparative analysis using real-world performance metrics', '[\"Select the optimal RAID level for a read-heavy database vs. a write-heavy transaction log.\"]', '[\"Fill out a comparison matrix of RAID levels based on cost, read speed, write speed, and redundancy.\"]', '[\"Disaster simulation: \'Drive failure!\' Students calculate if data is lost under different RAID configurations.\"]', NULL, '{\"session_number\":50,\"unit_id\":null,\"title\":\"File Organizations & RAID Levels\",\"duration_mins\":60,\"objectives\":[\"Compare Heap, Sequential, and Hashing file organizations.\",\"Analyze RAID levels (RAID 0, 1, 5, 10) for performance and fault tolerance.\"],\"teaching_method\":\"Comparative analysis using real-world performance metrics\",\"activities\":[\"Select the optimal RAID level for a read-heavy database vs. a write-heavy transaction log.\"],\"formative_assessment\":[\"Fill out a comparison matrix of RAID levels based on cost, read speed, write speed, and redundancy.\"],\"engagement\":[\"Disaster simulation: \'Drive failure!\' Students calculate if data is lost under different RAID configurations.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(51, 1, 3, NULL, 51, 'Indexing Concepts: Primary, Secondary, and Clustered Indexes', 60, '[\"Explain the purpose of database indexing.\",\"Differentiate between primary, secondary, and clustered indexes.\"]', 'Visual diagramming and conceptual walkthroughs', '[\"Draw the pointer structures for a primary index and a secondary index on a sample data file.\"]', '[\"Identify which index type is best suited for a given search key and file organization.\"]', '[\"Book index analogy: Comparing a primary index to a book\'s table of contents and a secondary index to the back-of-the-book index.\"]', NULL, '{\"session_number\":51,\"unit_id\":null,\"title\":\"Indexing Concepts: Primary, Secondary, and Clustered Indexes\",\"duration_mins\":60,\"objectives\":[\"Explain the purpose of database indexing.\",\"Differentiate between primary, secondary, and clustered indexes.\"],\"teaching_method\":\"Visual diagramming and conceptual walkthroughs\",\"activities\":[\"Draw the pointer structures for a primary index and a secondary index on a sample data file.\"],\"formative_assessment\":[\"Identify which index type is best suited for a given search key and file organization.\"],\"engagement\":[\"Book index analogy: Comparing a primary index to a book\'s table of contents and a secondary index to the back-of-the-book index.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(52, 1, 3, NULL, 52, 'Sparse vs. Dense Indexes & Multi-level Indexing', 60, '[\"Compare sparse and dense index structures in terms of space and lookup time.\",\"Explain the necessity of multi-level indexing for large databases.\"]', 'Step-by-step mathematical calculations', '[\"Calculate the number of block accesses required to find a record with and without a multi-level index.\"]', '[\"Solve a problem on calculating the size of a index file given record and block sizes.\"]', '[\"Think-Pair-Share: Why can\'t we have a sparse index on a non-ordered file?\"]', NULL, '{\"session_number\":52,\"unit_id\":null,\"title\":\"Sparse vs. Dense Indexes & Multi-level Indexing\",\"duration_mins\":60,\"objectives\":[\"Compare sparse and dense index structures in terms of space and lookup time.\",\"Explain the necessity of multi-level indexing for large databases.\"],\"teaching_method\":\"Step-by-step mathematical calculations\",\"activities\":[\"Calculate the number of block accesses required to find a record with and without a multi-level index.\"],\"formative_assessment\":[\"Solve a problem on calculating the size of a index file given record and block sizes.\"],\"engagement\":[\"Think-Pair-Share: Why can\'t we have a sparse index on a non-ordered file?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(53, 1, 3, NULL, 53, 'Static and Dynamic Hashing', 60, '[\"Explain bucket overflow and collision resolution in static hashing.\",\"Implement dynamic hashing techniques (Extendible Hashing).\"]', 'Algorithmic dry-runs and interactive tracing', '[\"Perform step-by-step directory doubling and bucket splitting in Extendible Hashing.\"]', '[\"Draw the state of an Extendible Hash structure after inserting a sequence of keys.\"]', '[\"Interactive board work: Students take turns inserting keys and splitting buckets.\"]', NULL, '{\"session_number\":53,\"unit_id\":null,\"title\":\"Static and Dynamic Hashing\",\"duration_mins\":60,\"objectives\":[\"Explain bucket overflow and collision resolution in static hashing.\",\"Implement dynamic hashing techniques (Extendible Hashing).\"],\"teaching_method\":\"Algorithmic dry-runs and interactive tracing\",\"activities\":[\"Perform step-by-step directory doubling and bucket splitting in Extendible Hashing.\"],\"formative_assessment\":[\"Draw the state of an Extendible Hash structure after inserting a sequence of keys.\"],\"engagement\":[\"Interactive board work: Students take turns inserting keys and splitting buckets.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(54, 1, 3, NULL, 54, 'Tree-Structured Indexing: B-Trees', 60, '[\"Describe the structural properties of a B-Tree.\",\"Perform search and insertion operations in a B-Tree.\"]', 'Animation-based demonstration and step-by-step drawing', '[\"Construct a B-Tree of order 3 by inserting a sequence of integer keys.\"]', '[\"Identify invalid B-Tree structures based on node occupancy rules.\"]', '[\"Visual tracing: Students use an online interactive B-Tree visualization tool to verify their hand-drawn steps.\"]', NULL, '{\"session_number\":54,\"unit_id\":null,\"title\":\"Tree-Structured Indexing: B-Trees\",\"duration_mins\":60,\"objectives\":[\"Describe the structural properties of a B-Tree.\",\"Perform search and insertion operations in a B-Tree.\"],\"teaching_method\":\"Animation-based demonstration and step-by-step drawing\",\"activities\":[\"Construct a B-Tree of order 3 by inserting a sequence of integer keys.\"],\"formative_assessment\":[\"Identify invalid B-Tree structures based on node occupancy rules.\"],\"engagement\":[\"Visual tracing: Students use an online interactive B-Tree visualization tool to verify their hand-drawn steps.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(55, 1, 3, NULL, 55, 'Tree-Structured Indexing: B+ Trees', 60, '[\"Differentiate between B-Trees and B+ Trees.\",\"Perform insertion and deletion operations in a B+ Tree.\"]', 'Step-by-step tree modification exercises', '[\"Insert and delete keys from a B+ Tree, demonstrating node splitting and merging.\"]', '[\"Assignment on B+ Tree dry-runs (insertion and deletion steps).\"]', '[\"Peer check: Exchange hand-drawn B+ Trees after a deletion operation and verify node pointers.\"]', NULL, '{\"session_number\":55,\"unit_id\":null,\"title\":\"Tree-Structured Indexing: B+ Trees\",\"duration_mins\":60,\"objectives\":[\"Differentiate between B-Trees and B+ Trees.\",\"Perform insertion and deletion operations in a B+ Tree.\"],\"teaching_method\":\"Step-by-step tree modification exercises\",\"activities\":[\"Insert and delete keys from a B+ Tree, demonstrating node splitting and merging.\"],\"formative_assessment\":[\"Assignment on B+ Tree dry-runs (insertion and deletion steps).\"],\"engagement\":[\"Peer check: Exchange hand-drawn B+ Trees after a deletion operation and verify node pointers.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(56, 1, 3, NULL, 56, 'Database Recovery: Failure Classification', 60, '[\"Classify types of database failures (Transaction, System, Disk).\",\"Explain the role of the recovery manager in maintaining consistency.\"]', 'Interactive lecture and failure scenario mapping', '[\"Map real-world disaster scenarios (power cut, bad sector, software bug) to database failure classes.\"]', '[\"Short quiz on choosing the correct recovery strategy for different failure types.\"]', '[\"Brainstorming: What happens to active transactions when the power plug is pulled?\"]', NULL, '{\"session_number\":56,\"unit_id\":null,\"title\":\"Database Recovery: Failure Classification\",\"duration_mins\":60,\"objectives\":[\"Classify types of database failures (Transaction, System, Disk).\",\"Explain the role of the recovery manager in maintaining consistency.\"],\"teaching_method\":\"Interactive lecture and failure scenario mapping\",\"activities\":[\"Map real-world disaster scenarios (power cut, bad sector, software bug) to database failure classes.\"],\"formative_assessment\":[\"Short quiz on choosing the correct recovery strategy for different failure types.\"],\"engagement\":[\"Brainstorming: What happens to active transactions when the power plug is pulled?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(57, 1, 3, NULL, 57, 'Log-Based Recovery: Deferred Database Modification', 60, '[\"Explain the concept of write-ahead logging (WAL).\",\"Apply the Deferred Database Modification recovery algorithm after a system crash.\"]', 'Step-by-step walkthrough of log-based recovery scenarios', '[\"Analyze a sample transaction log and determine which transactions to REDO and which to ignore.\"]', '[\"Write the recovery actions (REDO list) for a given log file and crash point.\"]', '[\"Role-play: One student acts as the log writer, another as the database disk, coordinating writes.\"]', NULL, '{\"session_number\":57,\"unit_id\":null,\"title\":\"Log-Based Recovery: Deferred Database Modification\",\"duration_mins\":60,\"objectives\":[\"Explain the concept of write-ahead logging (WAL).\",\"Apply the Deferred Database Modification recovery algorithm after a system crash.\"],\"teaching_method\":\"Step-by-step walkthrough of log-based recovery scenarios\",\"activities\":[\"Analyze a sample transaction log and determine which transactions to REDO and which to ignore.\"],\"formative_assessment\":[\"Write the recovery actions (REDO list) for a given log file and crash point.\"],\"engagement\":[\"Role-play: One student acts as the log writer, another as the database disk, coordinating writes.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(58, 1, 3, NULL, 58, 'Log-Based Recovery: Immediate Database Modification', 60, '[\"Explain the immediate database modification technique.\",\"Apply UNDO and REDO operations to restore database consistency.\"]', 'Trace-based problem solving', '[\"Trace a recovery process requiring both UNDO and REDO operations from a log file.\"]', '[\"Construct the UNDO and REDO lists for a given immediate modification log.\"]', '[\"Interactive challenge: Find the mistake in a flawed UNDO\\/REDO recovery trace.\"]', NULL, '{\"session_number\":58,\"unit_id\":null,\"title\":\"Log-Based Recovery: Immediate Database Modification\",\"duration_mins\":60,\"objectives\":[\"Explain the immediate database modification technique.\",\"Apply UNDO and REDO operations to restore database consistency.\"],\"teaching_method\":\"Trace-based problem solving\",\"activities\":[\"Trace a recovery process requiring both UNDO and REDO operations from a log file.\"],\"formative_assessment\":[\"Construct the UNDO and REDO lists for a given immediate modification log.\"],\"engagement\":[\"Interactive challenge: Find the mistake in a flawed UNDO\\/REDO recovery trace.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(59, 1, 3, NULL, 59, 'Checkpoints in Recovery Systems', 60, '[\"Explain how checkpoints reduce recovery overhead.\",\"Perform recovery analysis on a log containing checkpoint records.\"]', 'Visual timeline analysis and problem-solving', '[\"Analyze a timeline of active transactions intersecting with a checkpoint and a system crash.\"]', '[\"Determine the starting point of log scanning during recovery when a checkpoint is present.\"]', '[\"Think-Pair-Share: Why can\'t we just checkpoint after every single transaction?\"]', NULL, '{\"session_number\":59,\"unit_id\":null,\"title\":\"Checkpoints in Recovery Systems\",\"duration_mins\":60,\"objectives\":[\"Explain how checkpoints reduce recovery overhead.\",\"Perform recovery analysis on a log containing checkpoint records.\"],\"teaching_method\":\"Visual timeline analysis and problem-solving\",\"activities\":[\"Analyze a timeline of active transactions intersecting with a checkpoint and a system crash.\"],\"formative_assessment\":[\"Determine the starting point of log scanning during recovery when a checkpoint is present.\"],\"engagement\":[\"Think-Pair-Share: Why can\'t we just checkpoint after every single transaction?\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42'),
(60, 1, 3, NULL, 60, 'Shadow Paging & Recovery Review', 60, '[\"Describe the shadow paging recovery technique.\",\"Compare log-based recovery with shadow paging.\"]', 'Comparative review and course wrap-up', '[\"Draw the directory structures of current and shadow page tables during a transaction.\"]', '[\"End-Semester mock questions on Log-based recovery, Checkpointing, and Shadow Paging.\"]', '[\"Jeopardy-style review game covering the key concepts of the entire course.\"]', NULL, '{\"session_number\":60,\"unit_id\":null,\"title\":\"Shadow Paging & Recovery Review\",\"duration_mins\":60,\"objectives\":[\"Describe the shadow paging recovery technique.\",\"Compare log-based recovery with shadow paging.\"],\"teaching_method\":\"Comparative review and course wrap-up\",\"activities\":[\"Draw the directory structures of current and shadow page tables during a transaction.\"],\"formative_assessment\":[\"End-Semester mock questions on Log-based recovery, Checkpointing, and Shadow Paging.\"],\"engagement\":[\"Jeopardy-style review game covering the key concepts of the entire course.\"]}', NULL, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-19 07:57:42');

-- --------------------------------------------------------

--
-- Table structure for table `marks_formulas`
--

CREATE TABLE `marks_formulas` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_type` varchar(20) DEFAULT NULL COMMENT 'theory|lab|NULL=all types',
  `subject_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL=department/type default; set=subject override',
  `name` varchar(120) NOT NULL,
  `pattern` varchar(80) DEFAULT NULL COMMENT 'Anna, Madurai, CBCS, etc.',
  `plain_english` text NOT NULL,
  `components` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '[{code,label,max,weight}]' CHECK (json_valid(`components`)),
  `expression` text DEFAULT NULL COMMENT 'Parsed calculation expression',
  `total_max` decimal(6,2) DEFAULT 25.00,
  `ai_parsed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_parsed`)),
  `is_default` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Tenant isolation',
  `type` varchar(60) NOT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'medium' COMMENT 'high|medium|low',
  `title` varchar(200) NOT NULL,
  `body` text DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `action_type` varchar(60) DEFAULT NULL,
  `action_payload` longtext DEFAULT NULL COMMENT 'JSON action metadata',
  `delivery_status` longtext DEFAULT NULL COMMENT 'JSON channel delivery statuses',
  `is_read` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `institution_id`, `type`, `priority`, `title`, `body`, `action_url`, `action_type`, `action_payload`, `delivery_status`, `is_read`, `meta`, `created_at`) VALUES
(1, 4, NULL, 'assignment_deadline', 'medium', 'Case Study Assignment: E-Commerce Database Optimization and Normalization — Deadline today', 'Deadline today [asg-deadline-1-today]', '/professor/student/assignments.php', NULL, NULL, NULL, 0, '{\"marker\":\"asg-deadline-1-today\",\"assignment_id\":1,\"bucket\":\"today\"}', '2026-09-19 08:05:50'),
(2, 2, 1, 'approval', 'high', 'Course plan submitted', 'A faculty plan awaits review.', '/hod/approvals.php', 'APPROVE_PLAN', '{\"type\":\"APPROVE_PLAN\",\"record_id\":1}', '{\"in_app\":{\"status\":\"delivered\",\"at\":\"2026-09-29 09:26:54\"},\"email\":{\"status\":\"disabled\",\"at\":null},\"whatsapp\":{\"status\":\"not_configured\",\"at\":null},\"sms\":{\"status\":\"not_configured\",\"at\":null}}', 0, '{\"category\":\"approvals\",\"digest_mode\":\"immediate\"}', '2026-09-29 03:56:54'),
(3, 2, 1, 'approval', 'high', 'Course plan submitted', 'A faculty plan awaits review.', '/hod/approvals.php', 'APPROVE_PLAN', '{\"type\":\"APPROVE_PLAN\",\"record_id\":2}', '{\"in_app\":{\"status\":\"delivered\",\"at\":\"2026-09-29 09:48:06\"},\"email\":{\"status\":\"disabled\",\"at\":null},\"whatsapp\":{\"status\":\"not_configured\",\"at\":null},\"sms\":{\"status\":\"not_configured\",\"at\":null}}', 1, '{\"category\":\"approvals\",\"digest_mode\":\"immediate\"}', '2026-09-29 04:18:06'),
(4, 5, 1, 'approval', 'high', 'Plan approved', 'HOD reviewed this course plan.', '/plan-view.php?id=2', 'VIEW_PLAN', '{\"type\":\"VIEW_PLAN\",\"record_id\":2}', '{\"in_app\":{\"status\":\"delivered\",\"at\":\"2026-09-29 13:06:44\"},\"email\":{\"status\":\"disabled\",\"at\":null},\"whatsapp\":{\"status\":\"not_configured\",\"at\":null},\"sms\":{\"status\":\"not_configured\",\"at\":null}}', 1, '{\"category\":\"approvals\",\"digest_mode\":\"immediate\"}', '2026-09-29 07:36:44'),
(16, 4, 1, 'announcement', 'medium', 'Holiday Notice', 'This Week Friday Going to be Holiday Because of Gandhi Jayanthi\n\nFrom: College Admin (College Admin)', '/student/notifications', 'OPEN_NOTIFICATIONS', '{\"type\":\"OPEN_NOTIFICATIONS\"}', '{\"in_app\":{\"status\":\"delivered\",\"at\":\"2026-09-30 09:20:19\"},\"email\":{\"status\":\"disabled\",\"at\":null},\"whatsapp\":{\"status\":\"not_configured\",\"at\":null},\"sms\":{\"status\":\"not_configured\",\"at\":null}}', 0, '{\"announcement_id\":8,\"admin_id\":1,\"kind\":\"admin_audience_message\",\"audience\":\"ALL_STUDENTS\",\"notice_type\":\"EVENT\",\"has_attachment\":false,\"attachment_original_name\":null,\"sender_name\":\"College Admin\",\"category\":\"system\",\"digest_mode\":\"immediate\"}', '2026-09-30 03:50:19'),
(18, 4, 1, 'announcement', 'medium', 'DeadLine Announcement', 'Tomorrow Assignment Submission Last date\n\nCSE01 · Database Management Systems\n1st Year · UG · Year 1 · Sec A (CSE)\nFrom: sandra', '/student/notifications.php', 'OPEN_NOTIFICATIONS', '{\"type\":\"OPEN_NOTIFICATIONS\"}', '{\"in_app\":{\"status\":\"delivered\",\"at\":\"2026-09-30 09:38:44\"},\"email\":{\"status\":\"disabled\",\"at\":null},\"whatsapp\":{\"status\":\"not_configured\",\"at\":null},\"sms\":{\"status\":\"not_configured\",\"at\":null}}', 0, '{\"announcement_id\":1,\"professor_id\":3,\"subject_id\":1,\"class_id\":1,\"year\":1,\"course_label\":\"CSE01 · Database Management Systems\",\"class_label\":\"UG · Year 1 · Sec A (CSE)\",\"kind\":\"professor_student_message\",\"has_attachment\":false,\"attachment_original_name\":null,\"category\":\"system\",\"digest_mode\":\"immediate\"}', '2026-09-30 04:08:44'),
(19, 4, NULL, 'assignment_deadline', 'medium', 'Case Study: Architectural Analysis and Kernel-Mode Transition Failures in Enterprise Operating Systems — Deadline today', 'Deadline today [asg-deadline-2-today]', '/professor/student/assignments.php', NULL, NULL, NULL, 0, '{\"marker\":\"asg-deadline-2-today\",\"assignment_id\":2,\"bucket\":\"today\"}', '2026-09-30 05:20:28');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plan_reviews`
--

CREATE TABLE `plan_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL,
  `reviewer_id` int(10) UNSIGNED NOT NULL,
  `action` enum('approve','reject','request_changes','comment') NOT NULL,
  `comments` text DEFAULT NULL,
  `checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plan_reviews`
--

INSERT INTO `plan_reviews` (`id`, `plan_id`, `reviewer_id`, `action`, `comments`, `checklist`, `created_at`) VALUES
(1, 2, 2, 'approve', 'HOD reviewed this course plan.', '{\"overall\":\"\",\"points\":[]}', '2026-09-29 07:36:44');

-- --------------------------------------------------------

--
-- Table structure for table `plan_units`
--

CREATE TABLE `plan_units` (
  `id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL,
  `unit_number` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `hours` decimal(5,1) DEFAULT 0.0,
  `topics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`topics`)),
  `outcomes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`outcomes`)),
  `bloom_k_level` varchar(10) DEFAULT NULL,
  `bloom_map` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bloom_map`)),
  `teaching_methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`teaching_methods`)),
  `assessment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assessment`)),
  `resources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resources`)),
  `sort_order` int(11) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plan_units`
--

INSERT INTO `plan_units` (`id`, `plan_id`, `unit_number`, `title`, `hours`, `topics`, `outcomes`, `bloom_k_level`, `bloom_map`, `teaching_methods`, `assessment`, `resources`, `sort_order`, `meta`) VALUES
(1, 1, 1, 'Introduction to DBMS & Database Architecture', 12.0, '[\"Data vs Information, File-Processing Systems vs DBMS, Advantages of DBMS\",\"Data Abstraction, Data Independence (Physical and Logical)\",\"Three-Schema Architecture (Internal, Conceptual, External)\",\"Database Languages (DDL, DML, DCL, TCL), Database Interfaces\",\"Roles of Database Users and DBAs\",\"ER Model: Entities, Attributes, Entity Sets, Relationships, Mapping Cardinalities, Keys (Super, Candidate, Primary, Foreign)\",\"Extended ER (EER) Modeling: Specialization, Generalization, Aggregation\",\"Reduction of ER\\/EER Diagrams to Relational Tables\"]', '[\"Differentiate between traditional file systems and modern DBMS architectures.\",\"Design conceptual schemas using ER and EER diagrams for real-world enterprise scenarios.\",\"Map complex ER\\/EER diagrams into structurally sound relational database tables.\"]', 'K3', NULL, '[\"Chalk and Board for architectural diagrams\",\"Collaborative group activity for ER modeling of real-world case studies\",\"Flipped classroom on DBMS vs File Systems\"]', '[\"Class Test on ER-to-Relational mapping rules\",\"Evaluation of ER diagram design assignment for a given case study\"]', NULL, 0, NULL),
(2, 1, 2, 'Relational Model & Query Languages', 12.0, '[\"Structure of Relational Databases, Relational Model Constraints (Domain, Entity Integrity, Referential Integrity)\",\"Relational Algebra: Fundamental Operations (Select, Project, Union, Set Difference, Cartesian Product, Rename)\",\"Relational Algebra: Additional Operations (Join, Division, Intersection)\",\"SQL DDL Commands (CREATE, ALTER, DROP, TRUNCATE) and DML Commands (INSERT, UPDATE, DELETE, SELECT)\",\"Integrity Constraints: NOT NULL, UNIQUE, PRIMARY KEY, FOREIGN KEY, CHECK, DEFAULT\",\"Aggregate Functions, Grouping (GROUP BY, HAVING), Nested Subqueries, Set Operations, Inner\\/Outer Joins\",\"Views & Indexes: Creating Views, Updatable Views, Index Structures\"]', '[\"Formulate formal relational algebra expressions for complex data retrieval queries.\",\"Construct robust SQL queries utilizing joins, nested subqueries, and aggregation to solve business logic requirements.\",\"Implement and enforce domain, entity, and referential integrity constraints in SQL.\"]', 'K4', NULL, '[\"Hands-on laboratory sessions using PostgreSQL\\/MySQL\",\"Problem-solving sessions for Relational Algebra\",\"Interactive live-coding demonstrations\"]', '[\"Laboratory practical exam on SQL queries and Joins\",\"Online coding challenge on nested subqueries and constraints\"]', NULL, 1, NULL),
(3, 1, 3, 'Relational Database Design & Normalization', 12.0, '[\"Pitfalls in Relational Design: Data Redundancy, Insertion, Deletion, and Update Anomalies\",\"Functional Dependencies: Definition, Trivial and Non-trivial Dependencies, Closure of Functional Dependencies, Armstrong\\u2019s Axioms, Canonical Cover\",\"First Normal Form (1NF), Second Normal Form (2NF), Third Normal Form (3NF)\",\"Boyce-Codd Normal Form (BCNF)\",\"Higher Normal Forms: Fourth Normal Form (4NF - Multivalued Dependencies), Fifth Normal Form (5NF - Join Dependencies)\",\"Decomposition Properties: Lossless-Join Decomposition, Dependency-Preserving Decomposition\"]', '[\"Identify and analyze design anomalies in poorly structured relational schemas.\",\"Compute functional dependency closures, canonical covers, and candidate keys for relational schemas.\",\"Decompose relational schemas up to BCNF\\/5NF ensuring lossless-join and dependency-preservation properties.\"]', 'K4', NULL, '[\"Problem-driven learning using step-by-step normalization exercises\",\"Peer instruction on decomposition properties\",\"Guided design reviews of student-created schemas\"]', '[\"Mid-Semester Examination containing analytical normalization problems\",\"Home assignment on finding Canonical Cover and testing Lossless-Join property\"]', NULL, 2, NULL),
(4, 1, 4, 'Transaction Management & Concurrency Control', 12.0, '[\"Transaction Concepts: Definition, Transaction States, ACID Properties\",\"Schedules: Concurrent Executions, Serial Schedules, Serializability (Conflict and View Serializability)\",\"Recoverability: Cascadeless and Recoverable Schedules\",\"Lock-Based Protocols: Shared\\/Exclusive Locks, Two-Phase Locking (2PL - Strict 2PL, Rigorous 2PL)\",\"Deadlocks: Deadlock Prevention, Detection, and Recovery\",\"Timestamp-Based & Validation-Based Protocols, Thomas\' Write Rule\"]', '[\"Analyze concurrent transaction schedules for conflict and view serializability.\",\"Apply lock-based and timestamp-based protocols to guarantee database consistency and isolation.\",\"Formulate strategies to prevent, detect, and resolve deadlocks in concurrent transaction environments.\"]', 'K4', NULL, '[\"Visual tracing of concurrent execution timelines\",\"Case-study analysis of financial transaction failures\",\"Interactive simulation tools for Lock-based protocols\"]', '[\"Quizzes on Serializability and 2PL execution paths\",\"Analytical problem-solving test on Deadlock detection algorithms\"]', NULL, 3, NULL),
(5, 1, 5, 'Storage Structures, Indexing & Recovery', 12.0, '[\"Storage Architecture: File Organization (Sequential, Heap, Hashing), RAID Levels\",\"Indexing & Hashing: Primary, Secondary, Clustered Index, Sparse vs Dense Index\",\"Dynamic Hashing, Tree-Structured Indexing (B-Trees and B+-Trees)\",\"Database Recovery Techniques: Failure Classification (System Crash, Transaction Failure, Disk Failure)\",\"Log-Based Recovery (Deferred Database Modification, Immediate Database Modification)\",\"Checkpoints and Shadow Paging\"]', '[\"Compare different physical file organizations and RAID levels for optimal database performance.\",\"Construct and manipulate B-Trees and B+-Trees for indexing database attributes.\",\"Evaluate recovery algorithms (Deferred vs Immediate modifications) to restore database consistency after failures.\"]', 'K3', NULL, '[\"Animation-based demonstration of B+ Tree insertions and deletions\",\"Comparative analysis of RAID levels using real-world performance metrics\",\"Step-by-step walkthrough of log-based recovery scenarios\"]', '[\"Assignment on B+ Tree dry-runs (insertion\\/deletion steps)\",\"End-Semester Examination questions on Log-based recovery and Checkpointing\"]', NULL, 4, NULL),
(6, 2, 1, 'Introduction to Operating Systems', 11.0, '[\"Computer System Overview: Elements, organization, architecture, and multi-core systems.\",\"Operating System Basics: Objectives, functions, and historical evolution of operating systems.\",\"System Structures: OS services, user interface, system calls, and system programs.\",\"Design and Implementation: Structuring methods and virtual machines introduction.\"]', '[\"Identify the core components and architectural elements of a computer system.\",\"Explain the services, functions, and structural design of modern operating systems.\",\"Differentiate between system calls and system programs, and understand their execution flow.\"]', 'K2', NULL, '[\"Chalk and Talk\",\"PowerPoint Presentations\",\"Collaborative Learning (Think-Pair-Share on System Calls)\"]', '[\"Class Test on OS Structures\",\"Assignment on System Calls tracing in Linux\"]', NULL, 0, NULL),
(7, 2, 2, 'Process Management & Synchronization', 14.0, '[\"Process Concept: Process scheduling, operations on processes, and Inter-Process Communication (IPC).\",\"CPU Scheduling: Scheduling criteria and scheduling algorithms (FCFS, SJF, Priority, Round Robin).\",\"Threads: Multithreaded models and threading issues.\",\"Process Synchronization: The critical-section problem, synchronization hardware, semaphores, mutex locks, and classical synchronization problems.\",\"Deadlocks: Characterization, methods for handling deadlocks, prevention, avoidance, detection, and recovery.\"]', '[\"Analyze and compare various CPU scheduling algorithms based on performance criteria.\",\"Solve classical synchronization problems using semaphores and mutex locks.\",\"Apply Banker\'s algorithm for deadlock avoidance and analyze deadlock recovery strategies.\"]', 'K4', NULL, '[\"Interactive Lectures\",\"Problem-Solving Sessions (Scheduling & Deadlocks)\",\"Coding Demonstrations (POSIX Threads in C)\"]', '[\"Mid-Term Examination\",\"Programming Assignment: Simulation of CPU Scheduling Algorithms\",\"Quiz on Process Synchronization\"]', NULL, 1, NULL),
(8, 2, 3, 'Memory Management', 12.0, '[\"Main Memory: Background, swapping, contiguous memory allocation, and paging.\",\"Page Tables & Segmentation: Structure of page tables and segmentation with paging.\",\"Virtual Memory: Demand paging, copy-on-write, page replacement algorithms (FIFO, LRU, Optimal), and allocation of frames.\",\"Performance: Thrashing and memory-mapped files.\"]', '[\"Contrast contiguous and non-contiguous memory allocation techniques.\",\"Calculate physical addresses from logical addresses using paging and segmentation schemes.\",\"Evaluate and compare the performance of various page replacement algorithms.\"]', 'K5', NULL, '[\"Flipped Classroom (Paging concepts)\",\"Analytical Problem Solving (Address Translation)\",\"Visual Demonstrations of Page Replacement\"]', '[\"Analytical Assignment on Address Translation and Page Tables\",\"Simulation Project: Page Replacement Algorithms\",\"In-class Problem Solving Test\"]', NULL, 2, NULL),
(9, 2, 4, 'Storage Management & I/O Systems', 12.0, '[\"Mass-Storage Structure: Disk structure, disk scheduling algorithms, and disk management.\",\"File System Interface: File concept, access methods, directory structure, file system mounting, sharing, and protection.\",\"File System Implementation: File-system structure, directory implementation, allocation methods (contiguous, linked, indexed), and free-space management.\",\"I\\/O Systems: I\\/O hardware, application I\\/O interfaces, and kernel I\\/O subsystems.\"]', '[\"Analyze and implement disk scheduling algorithms to optimize I\\/O performance.\",\"Compare different directory structures and file allocation methods.\",\"Explain the role of kernel I\\/O subsystems, buffering, and caching.\"]', 'K4', NULL, '[\"Lectures\",\"Case Studies of Ext4 and NTFS File Systems\",\"Group Discussion on Disk Scheduling Efficiency\"]', '[\"Home Assignment on File Allocation Methods\",\"Written Test on Disk Scheduling Algorithms\",\"Viva-voce on File System Implementation\"]', NULL, 3, NULL),
(10, 2, 5, 'Virtualization and Mobile Operating Systems', 11.0, '[\"Virtual Machines: History, benefits, features, building blocks, and types of virtual machines and their implementations.\",\"Virtualization: OS components and support for virtualization.\",\"Mobile Operating Systems: Architecture, features, and security models of modern mobile operating systems (focused on Android and iOS).\"]', '[\"Distinguish between Type-1 and Type-2 hypervisors and their deployment scenarios.\",\"Explain the architectural differences between desktop\\/server OS and mobile OS.\",\"Analyze the security models and permission frameworks of Android and iOS.\"]', 'K4', NULL, '[\"Technical Seminars\",\"Comparative Case Studies (Android vs. iOS)\",\"Hands-on Demo of VirtualBox\\/VMware\"]', '[\"Technical Presentation on Virtualization Technologies\",\"Comparative Report on Mobile OS Security Models\",\"End-Semester Theory Examination\"]', NULL, 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `presentations`
--

CREATE TABLE `presentations` (
  `id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED DEFAULT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slide_count` int(10) UNSIGNED DEFAULT 0,
  `slides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`slides`)),
  `status` enum('draft','ready','published') DEFAULT 'draft',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `professor_ai_settings`
--

CREATE TABLE `professor_ai_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `provider` varchar(32) NOT NULL,
  `model` varchar(120) NOT NULL,
  `encrypted_api_key` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `professor_ai_settings`
--

INSERT INTO `professor_ai_settings` (`id`, `professor_id`, `provider`, `model`, `encrypted_api_key`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 3, 'gemini', 'gemini-3.8-flash', 'dwAaa/mPpGdyWOOJFbHidJF8/S0TgBI+gb4m0M/0iO3g6k5HEdSHt9rEB4ZW2+1XurNlLzJa8k00wRRwWiYiGijuFBWm/4vl3q8/R/Kh84Cy', 1, '2026-09-28 05:31:09', '2026-09-28 05:31:09'),
(5, 5, 'gemini', 'gemini-3.8-flash', 'JnRs4QqRti8XqE+4/2j6FN1vv3ZEV2TgS+2XGo04y59k2rLGmDWlGfTuHy5kbuV2JEhEo/i0L4ovQ8r487iCwg7FQbikqajH7YegqObdpbXv', 1, '2026-09-29 04:07:32', '2026-09-29 04:07:32');

-- --------------------------------------------------------

--
-- Table structure for table `professor_announcements`
--

CREATE TABLE `professor_announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `year` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `recipient_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_mime_type` varchar(100) DEFAULT NULL,
  `attachment_size` int(10) UNSIGNED DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `professor_announcements`
--

INSERT INTO `professor_announcements` (`id`, `institution_id`, `department_id`, `professor_id`, `subject_id`, `class_id`, `year`, `title`, `body`, `recipient_count`, `attachment_path`, `attachment_original_name`, `attachment_mime_type`, `attachment_size`, `meta`, `created_at`) VALUES
(1, 1, 1, 3, 1, 1, 1, 'DeadLine Announcement', 'Tomorrow Assignment Submission Last date', 1, NULL, NULL, NULL, NULL, '{\"course_label\":\"CSE01 · Database Management Systems\",\"class_label\":\"UG · Year 1 · Sec A (CSE)\",\"year_label\":\"1st Year\",\"sender_name\":\"sandra\",\"has_attachment\":false,\"attachment_original_name\":null}', '2026-09-30 04:08:43');

-- --------------------------------------------------------

--
-- Table structure for table `professor_hod_messages`
--

CREATE TABLE `professor_hod_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `thread_id` int(10) UNSIGNED DEFAULT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `hod_id` int(10) UNSIGNED NOT NULL,
  `sender_role` enum('professor','hod') NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_mime_type` varchar(100) DEFAULT NULL,
  `attachment_size` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `level` enum('UG','PG','Diploma','PhD','Other') DEFAULT 'UG',
  `duration_years` decimal(3,1) DEFAULT 3.0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `bank_id` int(10) UNSIGNED NOT NULL,
  `unit_number` int(10) UNSIGNED DEFAULT NULL,
  `question_type` enum('mcq','short','long','essay','case') NOT NULL,
  `bloom_k_level` varchar(10) DEFAULT NULL,
  `clo_code` varchar(20) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `marks` decimal(5,1) DEFAULT 1.0,
  `stem` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_answer` text DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `marking_scheme` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `bank_id`, `unit_number`, `question_type`, `bloom_k_level`, `clo_code`, `difficulty`, `marks`, `stem`, `options`, `correct_answer`, `explanation`, `marking_scheme`, `meta`, `created_at`) VALUES
(1, 1, 1, 'mcq', 'K2', 'CLO1', 'medium', 1.0, 'A database administrator decides to change the storage structure of a database from B-trees to hashing to improve query performance. If the conceptual schema and external views of the database remain completely unaffected by this change, which concept is being demonstrated?', '{\"A\":\"Logical Data Independence\",\"B\":\"Physical Data Independence\",\"C\":\"Schema Evolution\",\"D\":\"View Materialization\"}', 'B', 'Physical data independence is the ability to modify the physical schema (such as storage structures, file organizations, or indexes) without requiring changes to the conceptual or external schemas.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(2, 1, 1, 'mcq', 'K2', 'CLO1', 'medium', 1.0, 'In a traditional file-processing system, the same customer address might be stored in both a billing file and a shipping file. If the customer moves and only the billing file is updated, this leads to inconsistent data. How does a Database Management System (DBMS) primarily resolve this issue?', '{\"A\":\"By storing data in multiple redundant files and synchronizing them periodically using background batch processes.\",\"B\":\"By centralizing data storage so that a single logical representation of data is shared, minimizing redundancy and enforcing integrity constraints.\",\"C\":\"By converting all data into unstructured formats that do not require schema definitions or updates.\",\"D\":\"By forcing the application layer to handle all data validation and synchronization logic.\"}', 'B', 'A DBMS reduces data redundancy by integrating files into a single logical database, ensuring that updates to a data item are reflected across all views, thereby maintaining consistency.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(3, 1, 1, 'mcq', 'K2', 'CLO1', 'medium', 1.0, 'Which of the following scenarios best illustrates the role of the conceptual schema within the Three-Schema Architecture?', '{\"A\":\"A database designer defines the entities, relationships, constraints, and security rules for the entire enterprise database without specifying physical storage details.\",\"B\":\"A database programmer designs a customized user interface that displays only a subset of employee records based on department.\",\"C\":\"A system administrator configures the disk allocation, indexing strategies, and block sizes for the database files.\",\"D\":\"A data analyst writes an ad-hoc SQL query to extract monthly sales figures for a presentation.\"}', 'A', 'The conceptual schema describes the structure of the entire database for a community of users, focusing on entities, relationships, and constraints while hiding physical storage details.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(4, 1, 1, 'mcq', 'K2', 'CLO1', 'medium', 1.0, 'A database developer executes a command that alters the structure of an existing table by adding a new column for email addresses. What type of database language is being used, and what is its primary effect on the database?', '{\"A\":\"Data Manipulation Language (DML); it updates the actual data values stored in the table rows.\",\"B\":\"Data Control Language (DCL); it modifies the access privileges of users who can view the table.\",\"C\":\"Data Definition Language (DDL); it updates the database schema stored in the data dictionary.\",\"D\":\"Transaction Control Language (TCL); it ensures that the structural change is committed atomically.\"}', 'C', 'DDL commands (like ALTER TABLE) are used to define or modify the database schema, and their metadata is stored in the system catalog or data dictionary.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(5, 1, 1, 'mcq', 'K2', 'CLO1', 'medium', 1.0, 'In an ER model for a university, the \'Student\' entity set has attributes: Student_ID (unique), Email (unique), SSN (unique), Name, and Date_of_Birth. Which of the following statements correctly explains the relationship between candidate keys and the primary key for this entity set?', '{\"A\":\"Only Student_ID is a candidate key, and it must be chosen as the primary key.\",\"B\":\"Name and Date_of_Birth together form the only candidate key because they represent real-world attributes.\",\"C\":\"All attributes combined form a single candidate key, from which the primary key is extracted.\",\"D\":\"Student_ID, Email, and SSN are all candidate keys, and any one of them can be selected as the primary key.\"}', 'D', 'Candidate keys are minimal superkeys that uniquely identify an entity. Since Student_ID, Email, and SSN are all unique, they are all candidate keys, and the database designer can choose any one of them to be the primary key.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(6, 1, 1, 'mcq', 'K2', 'CLO1', 'easy', 1.0, 'Consider a relationship \'Manages\' between entity sets \'Manager\' and \'Department\'. If each manager can manage at most one department, and each department must be managed by exactly one manager, what is the mapping cardinality of this relationship?', '{\"A\":\"One-to-One (1:1)\",\"B\":\"One-to-Many (1:N)\",\"C\":\"Many-to-One (N:1)\",\"D\":\"Many-to-Many (M:N)\"}', 'A', 'Since a manager manages at most one department (1) and a department is managed by exactly one manager (1), the mapping cardinality is 1:1.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(7, 1, 1, 'mcq', 'K2', 'CLO1', 'medium', 1.0, 'An organization models its workforce by first identifying a general entity set \'Employee\' and then distinguishing subgroups \'Hourly_Employee\' and \'Salaried_Employee\' based on their payment structures. Which modeling process does this scenario represent, and what is its primary characteristic?', '{\"A\":\"Generalization; it is a bottom-up process that combines low-level entity sets into a high-level entity set.\",\"B\":\"Specialization; it is a top-down process that designates sub-groupings within an entity set based on distinguishing features.\",\"C\":\"Aggregation; it treats a relationship set as a high-level entity set to relate it to other entities.\",\"D\":\"Realization; it maps abstract logical schemas directly to physical storage structures.\"}', 'B', 'Specialization is a top-down process of identifying lower-level subgroups (Hourly_Employee, Salaried_Employee) within a higher-level entity set (Employee) based on specific characteristics.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(8, 1, 1, 'mcq', 'K2', 'CLO1', 'hard', 1.0, 'In an ER diagram, we want to model that an \'Employee\' works on a \'Project\', and this entire interaction requires the use of specific \'Equipment\'. Why is \'Aggregation\' used in this scenario instead of a simple ternary relationship?', '{\"A\":\"To represent that the \'Equipment\' entity set is a subclass of both \'Employee\' and \'Project\'.\",\"B\":\"To avoid redundancy by treating the relationship between \'Employee\' and \'Project\' as an abstract entity that can participate in a relationship with \'Equipment\'.\",\"C\":\"To enforce a constraint that an employee cannot work on a project unless they already own the equipment.\",\"D\":\"To convert a many-to-many relationship into two one-to-many relationships for easier database implementation.\"}', 'B', 'Aggregation is an abstraction through which relationships are treated as higher-level entities. It allows us to model a relationship between an entity set and another relationship set (e.g., relating \'Equipment\' to the \'Works-on\' relationship between \'Employee\' and \'Project\').', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(9, 1, 1, 'mcq', 'K2', 'CLO1', 'medium', 1.0, 'When converting an ER diagram to relational tables, how is a many-to-many (M:N) relationship set \'Enrolls\' between entity sets \'Student\' (primary key: Student_ID) and \'Course\' (primary key: Course_ID) typically represented?', '{\"A\":\"By adding Course_ID as a foreign key in the \'Student\' table.\",\"B\":\"By adding Student_ID as a foreign key in the \'Course\' table.\",\"C\":\"By creating a new table \'Enrolls\' whose primary key consists of the combination of Student_ID and Course_ID as foreign keys.\",\"D\":\"By merging the \'Student\' and \'Course\' tables into a single unified table with a composite primary key.\"}', 'C', 'An M:N relationship requires a separate junction table containing the primary keys of both participating entity sets as foreign keys. The combination of these foreign keys typically forms the primary key of the new table.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50'),
(10, 1, 1, 'mcq', 'K2', 'CLO1', 'easy', 1.0, 'Which of the following tasks is a primary responsibility of a Database Administrator (DBA) rather than an application programmer or a naive end-user?', '{\"A\":\"Authorizing access to the database, coordinating and monitoring its use, and acquiring software\\/hardware resources.\",\"B\":\"Writing parameterized SQL queries embedded in a Java application to retrieve customer profiles.\",\"C\":\"Entering daily sales transactions through a pre-designed web form interface.\",\"D\":\"Generating ad-hoc analytical reports using a spreadsheet tool connected to a read-only database view.\"}', 'A', 'The DBA is responsible for authorizing access to the database, coordinating and monitoring its use, and acquiring software and hardware resources as needed.', 'Correct option selected → 1 mark(s)\nIncorrect / blank → 0', '{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}', '2026-09-19 07:58:50');

-- --------------------------------------------------------

--
-- Table structure for table `question_attempts`
--

CREATE TABLE `question_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `score` decimal(6,2) DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question_banks`
--

CREATE TABLE `question_banks` (
  `id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED DEFAULT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `question_banks`
--

INSERT INTO `question_banks` (`id`, `plan_id`, `professor_id`, `subject_id`, `title`, `config`, `created_at`) VALUES
(1, 1, 3, NULL, 'MCQ · Database Management Systems · Unit 1 · K2', '{\"type\":\"mcq\",\"unit\":1,\"klevel\":\"K2\",\"count\":10,\"subject\":\"Database Management Systems\"}', '2026-09-19 07:58:50');

-- --------------------------------------------------------

--
-- Table structure for table `students_roster`
--

CREATE TABLE `students_roster` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `register_no` varchar(60) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `email` varchar(160) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students_roster`
--

INSERT INTO `students_roster` (`id`, `institution_id`, `class_id`, `user_id`, `register_no`, `full_name`, `email`, `phone`, `meta`, `is_active`) VALUES
(1, 1, 1, 4, '224026', 'Mohammed Abuthahir', 'abuthahirmohammed6@gmail.com', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `study_materials`
--

CREATE TABLE `study_materials` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `material_type` enum('notes','ppt') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_original_name` varchar(255) NOT NULL,
  `file_mime_type` varchar(120) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `study_materials`
--

INSERT INTO `study_materials` (`id`, `institution_id`, `professor_id`, `subject_id`, `class_id`, `title`, `description`, `material_type`, `file_path`, `file_original_name`, `file_mime_type`, `file_size`, `created_at`, `updated_at`) VALUES
(2, 1, 3, 1, 1, 'DBMS Unit 1 Notes', 'Study material covering DBMS fundamentals, database architecture and data models.', 'notes', 'study-materials/4a17a7cc0337bde0138c0e850c1f5e01.pdf', 'dbms-unit1-notes.pdf', 'application/pdf', 45, '2026-09-30 04:41:22', '2026-09-30 04:41:22'),
(3, 1, 3, 1, 1, 'DBMS', 'This DBMS QuestionBank', 'notes', 'study-materials/b80414cb8e5a494175fd50298765ed83.pdf', 'Python.pdf', 'application/pdf', 283777, '2026-09-30 04:46:00', '2026-09-30 04:46:00'),
(4, 1, 5, 2, 1, 'Operating System', NULL, 'notes', 'study-materials/5139404c5bba8d551692e7173704be60.pdf', 'Python.pdf', 'application/pdf', 283777, '2026-09-30 04:55:48', '2026-09-30 04:55:48');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(200) NOT NULL,
  `credits` decimal(4,1) DEFAULT 3.0,
  `contact_hours` int(10) UNSIGNED DEFAULT 45,
  `semester` varchar(40) DEFAULT NULL,
  `syllabus_text` longtext DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `institution_id`, `department_id`, `code`, `name`, `credits`, `contact_hours`, `semester`, `syllabus_text`, `meta`, `is_active`) VALUES
(1, 1, 1, 'CSE01', 'Database Management Systems', 4.0, 60, 'Odd Semester', NULL, '{\"year\":1,\"course_type\":\"theory\"}', 1),
(2, 1, 1, 'OS01', 'Operating Systems', 4.0, 60, 'Odd Semester', NULL, '{\"year\":1,\"course_type\":\"theory\"}', 1);

-- --------------------------------------------------------

--
-- Table structure for table `subject_assignments`
--

CREATE TABLE `subject_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` varchar(40) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subject_assignments`
--

INSERT INTO `subject_assignments` (`id`, `subject_id`, `professor_id`, `class_id`, `academic_year`, `semester`, `meta`) VALUES
(1, 1, 3, 1, '2026-27', 'Odd Semester', NULL),
(3, 2, 5, 1, '2026-27', 'Odd Semester', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `institution_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `role` enum('professor','student','hod','admin','superadmin') NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `employee_id` varchar(60) DEFAULT NULL,
  `register_no` varchar(60) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'For students',
  `academic_year_level` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Student academic year 1-4',
  `semester` varchar(40) DEFAULT NULL COMMENT 'Student Odd/Even semester',
  `designation` varchar(100) DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Expandable user attributes' CHECK (json_valid(`extra`)),
  `is_active` tinyint(1) DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `institution_id`, `department_id`, `role`, `email`, `password_hash`, `full_name`, `employee_id`, `register_no`, `phone`, `avatar_url`, `class_id`, `academic_year_level`, `semester`, `designation`, `preferences`, `extra`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'admin', 'admin@proprofessor.local', '$2y$10$xAyEPAOZAEOEbYNTxh5wTO9iBbQ2HrmpogYsezccaXsld0Je4Z0/a', 'College Admin', 'ADM001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-09-30 10:29:51', '2026-08-08 17:07:02', '2026-09-30 04:59:51'),
(2, 1, 1, 'hod', 'csehod@test.com', '$2y$10$ZTKVJEB2WM2/feKiENDkz.H4R5NbnjumjdvGMH55cQ0rIgPFv2NsW', 'Venketa lakshmi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"hod_alerts\":{\"plan_approved\":true,\"plan_rejected\":true,\"weekly_summary\":false,\"ai_complete\":true},\"digest_mode\":\"immediate\"}', NULL, 1, '2026-09-30 11:35:03', '2026-09-19 07:48:59', '2026-09-30 06:05:03'),
(3, 1, 1, 'professor', 'sandra@gmail.com', '$2y$10$7Sghy7t1iJCmOX6HSF/8auhx/Q.xRBCszcV/EZpRJM4pANL8ZHE/m', 'sandra', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"weekly_digest_cache\":{\"key\":\"W202640\",\"source\":\"stats\",\"lines\":[\"You conducted 0 class session(s) this week.\",\"0 assignment submission(s) were graded.\",\"0 assignment(s) were created.\",\"0 internal mark record(s) were updated.\",\"0 student(s) are currently at attendance risk.\",\"0 submission(s) are pending grading.\",\"0 course plan(s) were updated.\"]}}', NULL, 1, '2026-09-30 11:26:17', '2026-09-19 07:49:14', '2026-09-30 05:56:17'),
(4, 1, 1, 'student', 'abuthahirmohammed6@gmail.com', '$2y$10$SybQ2jKrCVqMWXhpW3TftetFtMf1sY2rAEDj4upmyyulAJrW/D0R6', 'Mohammed Abuthahir', NULL, '224026', NULL, NULL, 1, 1, 'Odd Semester', NULL, NULL, NULL, 1, '2026-09-30 10:20:50', '2026-09-19 07:50:40', '2026-09-30 04:50:50'),
(5, 1, 1, 'professor', 'sahana@gmail.com', '$2y$10$Ts8zILCbv2ke6Pi2jew2m.agMtstyUkjiLgvKkMxlPBTXDGS1JQCi', 'sahana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"weekly_digest_cache\":{\"key\":\"W202640\",\"source\":\"stats\",\"lines\":[\"You conducted 0 class session(s) this week.\",\"0 assignment submission(s) were graded.\",\"0 assignment(s) were created.\",\"0 internal mark record(s) were updated.\",\"0 student(s) are currently at attendance risk.\",\"0 submission(s) are pending grading.\",\"1 course plan(s) were updated.\"]}}', NULL, 1, '2026-09-30 10:48:02', '2026-09-29 03:59:15', '2026-09-30 05:18:02'),
(6, 1, 2, 'hod', 'vani@gmail.com', '$2y$10$SJITvO9ZsM9iUTxy4Aptdezy1iJjHAsRhXhUL0rWox.Em6hJjqDM6', 'Vanitha babu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-09-30 11:04:36', '2026-09-30 03:31:22', '2026-09-30 05:34:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_events`
--
ALTER TABLE `academic_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_act_inst` (`institution_id`,`created_at`);

--
-- Indexes for table `admin_hod_announcements`
--
ALTER TABLE `admin_hod_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aha_inst` (`institution_id`,`created_at`),
  ADD KEY `idx_aha_admin` (`admin_id`);

--
-- Indexes for table `ai_chats`
--
ALTER TABLE `ai_chats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_chat_messages`
--
ALTER TABLE `ai_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_msgs` (`chat_id`);

--
-- Indexes for table `ai_generations`
--
ALTER TABLE `ai_generations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_user` (`user_id`),
  ADD KEY `idx_ai_module` (`module`);

--
-- Indexes for table `ai_prompt_templates`
--
ALTER TABLE `ai_prompt_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_setting` (`institution_id`,`setting_key`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asg_prof` (`professor_id`);

--
-- Indexes for table `assignment_extension_requests`
--
ALTER TABLE `assignment_extension_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asg_ext_asg` (`assignment_id`),
  ADD KEY `idx_asg_ext_stu` (`student_id`),
  ADD KEY `idx_asg_ext_status` (`status`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_submission` (`assignment_id`,`student_id`);

--
-- Indexes for table `assignment_templates`
--
ALTER TABLE `assignment_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asg_tpl_prof` (`professor_id`),
  ADD KEY `idx_asg_tpl_inst` (`institution_id`);

--
-- Indexes for table `attendance_qr_tokens`
--
ALTER TABLE `attendance_qr_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_att_qr_token` (`token`),
  ADD KEY `idx_att_qr_session` (`session_id`),
  ADD KEY `idx_att_qr_class` (`class_id`,`subject_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_student` (`student_id`),
  ADD KEY `idx_att_session` (`session_id`);

--
-- Indexes for table `attendance_regularization_requests`
--
ALTER TABLE `attendance_regularization_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_reg_sess` (`session_id`),
  ADD KEY `idx_att_reg_stu` (`student_id`),
  ADD KEY `idx_att_reg_status` (`status`);

--
-- Indexes for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_att_session` (`class_id`,`subject_id`,`session_date`,`period`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `compliance_alerts`
--
ALTER TABLE `compliance_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `course_plans`
--
ALTER TABLE `course_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_course_plans_share_token` (`share_token`),
  ADD KEY `idx_plan_prof` (`professor_id`),
  ADD KEY `idx_plan_status` (`status`),
  ADD KEY `idx_plan_dept` (`department_id`);

--
-- Indexes for table `course_plan_versions`
--
ALTER TABLE `course_plan_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plan_ver` (`plan_id`,`version`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dept_inst` (`institution_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_chunks`
--
ALTER TABLE `document_chunks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chunk_doc` (`document_id`),
  ADD KEY `idx_chunk_inst` (`institution_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_enroll` (`student_id`,`subject_id`,`academic_year`);

--
-- Indexes for table `exam_papers`
--
ALTER TABLE `exam_papers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exam_prof` (`professor_id`),
  ADD KEY `idx_exam_inst` (`institution_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exp_inst` (`institution_id`,`expense_date`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feature_flags`
--
ALTER TABLE `feature_flags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `institutions`
--
ALTER TABLE `institutions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `institution_features`
--
ALTER TABLE `institution_features`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inst_feature` (`institution_id`,`feature_code`),
  ADD KEY `idx_feature_code` (`feature_code`);

--
-- Indexes for table `internal_marks`
--
ALTER TABLE `internal_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_marks` (`subject_id`,`class_id`,`register_no`,`academic_year`);

--
-- Indexes for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lesson_plan` (`plan_id`);

--
-- Indexes for table `marks_formulas`
--
ALTER TABLE `marks_formulas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`,`is_read`),
  ADD KEY `idx_notif_inst` (`institution_id`),
  ADD KEY `idx_notif_priority` (`user_id`,`priority`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `plan_reviews`
--
ALTER TABLE `plan_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plan_units`
--
ALTER TABLE `plan_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_unit_plan` (`plan_id`);

--
-- Indexes for table `presentations`
--
ALTER TABLE `presentations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `professor_ai_settings`
--
ALTER TABLE `professor_ai_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prof_ai` (`professor_id`),
  ADD KEY `idx_prof_ai_active` (`professor_id`,`is_active`);

--
-- Indexes for table `professor_announcements`
--
ALTER TABLE `professor_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ann_prof` (`professor_id`,`created_at`),
  ADD KEY `idx_ann_inst` (`institution_id`),
  ADD KEY `idx_ann_scope` (`subject_id`,`class_id`);

--
-- Indexes for table `professor_hod_messages`
--
ALTER TABLE `professor_hod_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phm_dept` (`department_id`,`created_at`),
  ADD KEY `idx_phm_prof` (`professor_id`,`created_at`),
  ADD KEY `idx_phm_hod` (`hod_id`,`created_at`),
  ADD KEY `idx_phm_thread` (`thread_id`,`id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prog_dept` (`department_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_q_bank` (`bank_id`),
  ADD KEY `idx_q_bloom` (`bloom_k_level`);

--
-- Indexes for table `question_attempts`
--
ALTER TABLE `question_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qa_q` (`question_id`),
  ADD KEY `idx_qa_inst` (`institution_id`);

--
-- Indexes for table `question_banks`
--
ALTER TABLE `question_banks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students_roster`
--
ALTER TABLE `students_roster`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roster` (`class_id`,`register_no`);

--
-- Indexes for table `study_materials`
--
ALTER TABLE `study_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sm_prof` (`professor_id`,`created_at`),
  ADD KEY `idx_sm_scope` (`institution_id`,`class_id`,`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subj_code_inst` (`institution_id`,`code`);

--
-- Indexes for table `subject_assignments`
--
ALTER TABLE `subject_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subj_prof_class` (`subject_id`,`professor_id`,`class_id`,`academic_year`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_user_inst_role` (`institution_id`,`role`),
  ADD KEY `idx_user_dept` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_events`
--
ALTER TABLE `academic_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `admin_hod_announcements`
--
ALTER TABLE `admin_hod_announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `ai_chats`
--
ALTER TABLE `ai_chats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_chat_messages`
--
ALTER TABLE `ai_chat_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_generations`
--
ALTER TABLE `ai_generations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ai_prompt_templates`
--
ALTER TABLE `ai_prompt_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_settings`
--
ALTER TABLE `app_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `assignment_extension_requests`
--
ALTER TABLE `assignment_extension_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `assignment_templates`
--
ALTER TABLE `assignment_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_qr_tokens`
--
ALTER TABLE `attendance_qr_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance_regularization_requests`
--
ALTER TABLE `attendance_regularization_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `compliance_alerts`
--
ALTER TABLE `compliance_alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_plans`
--
ALTER TABLE `course_plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `course_plan_versions`
--
ALTER TABLE `course_plan_versions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_chunks`
--
ALTER TABLE `document_chunks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `exam_papers`
--
ALTER TABLE `exam_papers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feature_flags`
--
ALTER TABLE `feature_flags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `institutions`
--
ALTER TABLE `institutions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `institution_features`
--
ALTER TABLE `institution_features`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `internal_marks`
--
ALTER TABLE `internal_marks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `marks_formulas`
--
ALTER TABLE `marks_formulas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plan_reviews`
--
ALTER TABLE `plan_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `plan_units`
--
ALTER TABLE `plan_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `presentations`
--
ALTER TABLE `presentations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `professor_ai_settings`
--
ALTER TABLE `professor_ai_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `professor_announcements`
--
ALTER TABLE `professor_announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `professor_hod_messages`
--
ALTER TABLE `professor_hod_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `question_attempts`
--
ALTER TABLE `question_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_banks`
--
ALTER TABLE `question_banks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students_roster`
--
ALTER TABLE `students_roster`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT for table `study_materials`
--
ALTER TABLE `study_materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subject_assignments`
--
ALTER TABLE `subject_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
