-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Aug 22, 2026 at 09:32 AM
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
(1, 1, 1, 'login', NULL, NULL, '::1', NULL, '2026-08-22 06:19:42'),
(2, 1, 1, 'logout', NULL, NULL, '::1', NULL, '2026-08-22 06:50:58');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 'Institution', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'trial', 60, NULL, NULL, '{\"attendance_min\": 75}', 1, '2026-08-08 17:07:02', '2026-08-22 06:17:44');

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
(10, 1, 'internal_marks', 0, NULL, NULL, '2026-08-12 07:43:15'),
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
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `type` varchar(60) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `marks` decimal(5,1) DEFAULT 1.0,
  `stem` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_answer` text DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
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

INSERT INTO `users` (`id`, `institution_id`, `department_id`, `role`, `email`, `password_hash`, `full_name`, `employee_id`, `register_no`, `phone`, `avatar_url`, `class_id`, `designation`, `preferences`, `extra`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'admin', 'admin@proprofessor.local', '$2y$10$xAyEPAOZAEOEbYNTxh5wTO9iBbQ2HrmpogYsezccaXsld0Je4Z0/a', 'College Admin', 'ADM001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-08-22 11:49:42', '2026-08-08 17:07:02', '2026-08-22 06:19:42');

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
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_submission` (`assignment_id`,`student_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_student` (`student_id`),
  ADD KEY `idx_att_session` (`session_id`);

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
  ADD KEY `idx_notif_user` (`user_id`,`is_read`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compliance_alerts`
--
ALTER TABLE `compliance_alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_plans`
--
ALTER TABLE `course_plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_plan_versions`
--
ALTER TABLE `course_plan_versions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marks_formulas`
--
ALTER TABLE `marks_formulas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plan_reviews`
--
ALTER TABLE `plan_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plan_units`
--
ALTER TABLE `plan_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `presentations`
--
ALTER TABLE `presentations`
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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_banks`
--
ALTER TABLE `question_banks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students_roster`
--
ALTER TABLE `students_roster`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subject_assignments`
--
ALTER TABLE `subject_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
