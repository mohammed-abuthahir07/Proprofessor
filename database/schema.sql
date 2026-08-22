-- ============================================================
-- ProProfessor AI — MySQL Schema (Expandable / Multi-tenant)
-- Compatible with MySQL 8.0+ / MariaDB 10.5+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `proprofessor`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `proprofessor`;

-- ------------------------------------------------------------
-- Feature flags (expandable options without schema migrations)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feature_flags` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`          VARCHAR(64) NOT NULL UNIQUE,
  `name`          VARCHAR(120) NOT NULL,
  `description`   TEXT NULL,
  `module`        VARCHAR(64) NOT NULL DEFAULT 'core',
  `is_enabled`    TINYINT(1) NOT NULL DEFAULT 1,
  `default_config` JSON NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `institution_features` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id`  INT UNSIGNED NOT NULL,
  `feature_code`    VARCHAR(64) NOT NULL,
  `is_enabled`      TINYINT(1) NOT NULL DEFAULT 1,
  `config`          JSON NULL,
  `enabled_at`      TIMESTAMP NULL,
  `disabled_at`     TIMESTAMP NULL,
  UNIQUE KEY `uq_inst_feature` (`institution_id`, `feature_code`),
  KEY `idx_feature_code` (`feature_code`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Institutions & academic structure
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `institutions` (
  `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`                  VARCHAR(200) NOT NULL,
  `code`                  VARCHAR(40) UNIQUE,
  `affiliation_university` VARCHAR(200) NULL,
  `naac_grade`            VARCHAR(20) NULL,
  `nba_status`            VARCHAR(40) NULL,
  `address`               TEXT NULL,
  `city`                  VARCHAR(80) NULL,
  `state`                 VARCHAR(80) NULL,
  `pincode`               VARCHAR(12) NULL,
  `phone`                 VARCHAR(30) NULL,
  `email`                 VARCHAR(120) NULL,
  `logo_url`              VARCHAR(255) NULL,
  `subscription_tier`     ENUM('starter','professional','enterprise','trial') DEFAULT 'trial',
  `licensed_seats`        INT UNSIGNED DEFAULT 60,
  `academic_year`         VARCHAR(20) NULL,
  `current_semester`      VARCHAR(40) NULL,
  `settings`              JSON NULL COMMENT 'Expandable institution settings',
  `is_active`             TINYINT(1) DEFAULT 1,
  `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `departments` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id`  INT UNSIGNED NOT NULL,
  `name`            VARCHAR(150) NOT NULL,
  `code`            VARCHAR(40) NULL,
  `hod_user_id`     INT UNSIGNED NULL,
  `meta`            JSON NULL,
  `is_active`       TINYINT(1) DEFAULT 1,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_dept_inst` (`institution_id`),
  CONSTRAINT `fk_dept_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `programs` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id`  INT UNSIGNED NOT NULL,
  `department_id`   INT UNSIGNED NOT NULL,
  `name`            VARCHAR(150) NOT NULL,
  `code`            VARCHAR(40) NULL,
  `level`           ENUM('UG','PG','Diploma','PhD','Other') DEFAULT 'UG',
  `duration_years`  DECIMAL(3,1) DEFAULT 3.0,
  `meta`            JSON NULL,
  `is_active`       TINYINT(1) DEFAULT 1,
  KEY `idx_prog_dept` (`department_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `classes` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id`  INT UNSIGNED NOT NULL,
  `department_id`   INT UNSIGNED NOT NULL,
  `program_id`      INT UNSIGNED NULL,
  `name`            VARCHAR(100) NOT NULL,
  `section`         VARCHAR(20) NULL,
  `year`            TINYINT UNSIGNED NULL,
  `semester`        VARCHAR(40) NULL,
  `academic_year`   VARCHAR(20) NULL,
  `meta`            JSON NULL,
  `is_active`       TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `subjects` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id`  INT UNSIGNED NOT NULL,
  `department_id`   INT UNSIGNED NOT NULL,
  `code`            VARCHAR(40) NOT NULL,
  `name`            VARCHAR(200) NOT NULL,
  `credits`         DECIMAL(4,1) DEFAULT 3.0,
  `contact_hours`   INT UNSIGNED DEFAULT 45,
  `semester`        VARCHAR(40) NULL,
  `syllabus_text`   LONGTEXT NULL,
  `meta`            JSON NULL,
  `is_active`       TINYINT(1) DEFAULT 1,
  UNIQUE KEY `uq_subj_code_inst` (`institution_id`, `code`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Users / profiles (4 roles)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id`  INT UNSIGNED NOT NULL,
  `department_id`   INT UNSIGNED NULL,
  `role`            ENUM('professor','student','hod','admin','superadmin') NOT NULL,
  `email`           VARCHAR(160) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `full_name`       VARCHAR(160) NOT NULL,
  `employee_id`     VARCHAR(60) NULL,
  `register_no`     VARCHAR(60) NULL,
  `phone`           VARCHAR(30) NULL,
  `avatar_url`      VARCHAR(255) NULL,
  `class_id`        INT UNSIGNED NULL COMMENT 'For students',
  `designation`     VARCHAR(100) NULL,
  `preferences`     JSON NULL,
  `extra`           JSON NULL COMMENT 'Expandable user attributes',
  `is_active`       TINYINT(1) DEFAULT 1,
  `last_login_at`   DATETIME NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_user_inst_role` (`institution_id`, `role`),
  KEY `idx_user_dept` (`department_id`),
  CONSTRAINT `fk_user_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token`      VARCHAR(100) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME NULL,
  KEY `idx_token` (`token`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `subject_assignments` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `subject_id`   INT UNSIGNED NOT NULL,
  `professor_id` INT UNSIGNED NOT NULL,
  `class_id`     INT UNSIGNED NULL,
  `academic_year` VARCHAR(20) NULL,
  `semester`     VARCHAR(40) NULL,
  `meta`         JSON NULL,
  UNIQUE KEY `uq_subj_prof_class` (`subject_id`, `professor_id`, `class_id`, `academic_year`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `enrollments` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`   INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NOT NULL,
  `class_id`     INT UNSIGNED NULL,
  `academic_year` VARCHAR(20) NULL,
  `semester`     VARCHAR(40) NULL,
  `status`       ENUM('active','dropped','completed') DEFAULT 'active',
  UNIQUE KEY `uq_enroll` (`student_id`, `subject_id`, `academic_year`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Course plans + versioning + Bloom's
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `course_plans` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id`  INT UNSIGNED NOT NULL,
  `department_id`   INT UNSIGNED NULL,
  `professor_id`    INT UNSIGNED NOT NULL,
  `subject_id`      INT UNSIGNED NULL,
  `class_id`        INT UNSIGNED NULL,
  `title`           VARCHAR(255) NOT NULL,
  `subject_name`    VARCHAR(200) NOT NULL,
  `subject_code`    VARCHAR(40) NULL,
  `credits`         DECIMAL(4,1) DEFAULT 3.0,
  `semester`        VARCHAR(40) NULL,
  `academic_year`   VARCHAR(20) NULL,
  `university`      VARCHAR(150) NULL,
  `syllabus_input`  LONGTEXT NULL,
  `status`          ENUM('draft','submitted','under_review','approved','returned') DEFAULT 'draft',
  `ai_score`        DECIMAL(5,2) NULL,
  `ai_review`       JSON NULL,
  `bloom_data`      JSON NULL,
  `weekly_plan`     JSON NULL,
  `resources`       JSON NULL,
  `expert_advice`   JSON NULL,
  `plan_data`       JSON NULL COMMENT 'Full structured plan payload',
  `version`         INT UNSIGNED DEFAULT 1,
  `parent_plan_id`  INT UNSIGNED NULL,
  `submitted_at`    DATETIME NULL,
  `reviewed_at`     DATETIME NULL,
  `reviewed_by`     INT UNSIGNED NULL,
  `hod_comments`    TEXT NULL,
  `meta`            JSON NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_plan_prof` (`professor_id`),
  KEY `idx_plan_status` (`status`),
  KEY `idx_plan_dept` (`department_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `course_plan_versions` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plan_id`      INT UNSIGNED NOT NULL,
  `version`      INT UNSIGNED NOT NULL,
  `snapshot`     JSON NOT NULL,
  `change_note`  TEXT NULL,
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_plan_ver` (`plan_id`, `version`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `plan_units` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plan_id`       INT UNSIGNED NOT NULL,
  `unit_number`   INT UNSIGNED NOT NULL,
  `title`         VARCHAR(255) NOT NULL,
  `hours`         DECIMAL(5,1) DEFAULT 0,
  `topics`        JSON NULL,
  `outcomes`      JSON NULL,
  `bloom_k_level` VARCHAR(10) NULL,
  `bloom_map`     JSON NULL,
  `teaching_methods` JSON NULL,
  `assessment`    JSON NULL,
  `resources`     JSON NULL,
  `sort_order`    INT DEFAULT 0,
  `meta`          JSON NULL,
  KEY `idx_unit_plan` (`plan_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `plan_reviews` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plan_id`      INT UNSIGNED NOT NULL,
  `reviewer_id`  INT UNSIGNED NOT NULL,
  `action`       ENUM('approve','reject','request_changes','comment') NOT NULL,
  `comments`     TEXT NULL,
  `checklist`    JSON NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Lesson plans
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lesson_plans` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plan_id`         INT UNSIGNED NOT NULL,
  `professor_id`    INT UNSIGNED NOT NULL,
  `unit_id`         INT UNSIGNED NULL,
  `session_number`  INT UNSIGNED NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `duration_mins`   INT UNSIGNED DEFAULT 60,
  `objectives`      JSON NULL,
  `teaching_method` VARCHAR(120) NULL,
  `activities`      JSON NULL,
  `formative_assessment` JSON NULL,
  `engagement`      JSON NULL,
  `materials`       JSON NULL,
  `content`         JSON NULL,
  `session_date`    DATE NULL,
  `meta`            JSON NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_lesson_plan` (`plan_id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Question bank
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_banks` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plan_id`      INT UNSIGNED NULL,
  `professor_id` INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NULL,
  `title`        VARCHAR(255) NOT NULL,
  `config`       JSON NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `questions` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bank_id`        INT UNSIGNED NOT NULL,
  `unit_number`    INT UNSIGNED NULL,
  `question_type`  ENUM('mcq','short','long','essay','case') NOT NULL,
  `bloom_k_level`  VARCHAR(10) NULL,
  `difficulty`     ENUM('easy','medium','hard') DEFAULT 'medium',
  `marks`          DECIMAL(5,1) DEFAULT 1,
  `stem`           TEXT NOT NULL,
  `options`        JSON NULL,
  `correct_answer` TEXT NULL,
  `explanation`    TEXT NULL,
  `meta`           JSON NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_q_bank` (`bank_id`),
  KEY `idx_q_bloom` (`bloom_k_level`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PPT generator
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `presentations` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plan_id`      INT UNSIGNED NULL,
  `professor_id` INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NULL,
  `title`        VARCHAR(255) NOT NULL,
  `slide_count`  INT UNSIGNED DEFAULT 0,
  `slides`       JSON NULL,
  `status`       ENUM('draft','ready','published') DEFAULT 'draft',
  `meta`         JSON NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Assignments + submissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `plan_id`        INT UNSIGNED NULL,
  `professor_id`   INT UNSIGNED NOT NULL,
  `subject_id`     INT UNSIGNED NULL,
  `class_id`       INT UNSIGNED NULL,
  `title`          VARCHAR(255) NOT NULL,
  `assignment_type` ENUM(
      'essay','case_study','research_review','problem_solving',
      'mini_project','mixed','lab','reflection','group_presentation'
    ) NOT NULL DEFAULT 'essay',
  `description`    LONGTEXT NULL,
  `rubric`         JSON NULL,
  `max_marks`      DECIMAL(6,2) DEFAULT 25,
  `deadline`       DATETIME NULL,
  `instructions`   JSON NULL,
  `ai_generated`   TINYINT(1) DEFAULT 0,
  `status`         ENUM('draft','published','closed') DEFAULT 'draft',
  `meta`           JSON NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_asg_prof` (`professor_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `student_id`    INT UNSIGNED NOT NULL,
  `content_text`  LONGTEXT NULL,
  `file_url`      VARCHAR(255) NULL,
  `submitted_at`  DATETIME NULL,
  `grade`         DECIMAL(6,2) NULL,
  `feedback`      TEXT NULL,
  `graded_by`     INT UNSIGNED NULL,
  `graded_at`     DATETIME NULL,
  `status`        ENUM('draft','submitted','late','graded','returned') DEFAULT 'draft',
  `meta`          JSON NULL,
  UNIQUE KEY `uq_submission` (`assignment_id`, `student_id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Attendance
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students_roster` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `class_id`     INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NULL,
  `register_no`  VARCHAR(60) NOT NULL,
  `full_name`    VARCHAR(160) NOT NULL,
  `email`        VARCHAR(160) NULL,
  `phone`        VARCHAR(30) NULL,
  `meta`         JSON NULL,
  `is_active`    TINYINT(1) DEFAULT 1,
  UNIQUE KEY `uq_roster` (`class_id`, `register_no`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `attendance_sessions` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `professor_id` INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NULL,
  `class_id`     INT UNSIGNED NOT NULL,
  `session_date` DATE NOT NULL,
  `period`       VARCHAR(40) NULL,
  `topic`        VARCHAR(255) NULL,
  `records`      JSON NOT NULL COMMENT '[{student_id/reg, status}]',
  `present_count` INT UNSIGNED DEFAULT 0,
  `absent_count`  INT UNSIGNED DEFAULT 0,
  `meta`         JSON NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_att_session` (`class_id`, `subject_id`, `session_date`, `period`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id`   INT UNSIGNED NOT NULL,
  `student_id`   INT UNSIGNED NULL,
  `register_no`  VARCHAR(60) NOT NULL,
  `status`       ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `meta`         JSON NULL,
  KEY `idx_att_student` (`student_id`),
  KEY `idx_att_session` (`session_id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Internal marks + configurable formulas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `marks_formulas` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id`  INT UNSIGNED NOT NULL,
  `department_id`   INT UNSIGNED NULL,
  `name`            VARCHAR(120) NOT NULL,
  `pattern`         VARCHAR(80) NULL COMMENT 'Anna, Madurai, CBCS, etc.',
  `plain_english`   TEXT NOT NULL,
  `components`      JSON NOT NULL COMMENT '[{code,label,max,weight}]',
  `expression`      TEXT NULL COMMENT 'Parsed calculation expression',
  `total_max`       DECIMAL(6,2) DEFAULT 25,
  `ai_parsed`       JSON NULL,
  `is_default`      TINYINT(1) DEFAULT 0,
  `meta`            JSON NULL,
  `created_by`      INT UNSIGNED NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `internal_marks` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `professor_id`  INT UNSIGNED NOT NULL,
  `subject_id`    INT UNSIGNED NOT NULL,
  `class_id`      INT UNSIGNED NOT NULL,
  `formula_id`    INT UNSIGNED NULL,
  `student_id`    INT UNSIGNED NULL,
  `register_no`   VARCHAR(60) NOT NULL,
  `student_name`  VARCHAR(160) NOT NULL,
  `marks_data`    JSON NOT NULL COMMENT 'component => value',
  `attendance_pct` DECIMAL(5,2) NULL,
  `assignment_total` DECIMAL(6,2) NULL,
  `computed_total` DECIMAL(6,2) NULL,
  `grade_letter`  VARCHAR(5) NULL,
  `meta`          JSON NULL,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_marks` (`subject_id`, `class_id`, `register_no`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Notes / documents / RAG chunks
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `owner_id`       INT UNSIGNED NOT NULL,
  `plan_id`        INT UNSIGNED NULL,
  `subject_id`     INT UNSIGNED NULL,
  `doc_type`       ENUM('note','ppt','syllabus','circular','naac','other') DEFAULT 'note',
  `title`          VARCHAR(255) NOT NULL,
  `file_url`       VARCHAR(255) NULL,
  `unit_number`    INT UNSIGNED NULL,
  `content_text`   LONGTEXT NULL,
  `is_published`   TINYINT(1) DEFAULT 0,
  `meta`           JSON NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `document_chunks` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `document_id`    INT UNSIGNED NOT NULL,
  `institution_id` INT UNSIGNED NOT NULL,
  `chunk_index`    INT UNSIGNED NOT NULL,
  `content_chunk`  TEXT NOT NULL,
  `embedding_json` JSON NULL COMMENT 'Gemini embedding vector (expandable)',
  `meta`           JSON NULL,
  KEY `idx_chunk_doc` (`document_id`),
  KEY `idx_chunk_inst` (`institution_id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Notifications, announcements, calendar
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(60) NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `body`       TEXT NULL,
  `action_url` VARCHAR(255) NULL,
  `is_read`    TINYINT(1) DEFAULT 0,
  `meta`       JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notif_user` (`user_id`, `is_read`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `department_id`  INT UNSIGNED NULL,
  `created_by`     INT UNSIGNED NOT NULL,
  `title`          VARCHAR(200) NOT NULL,
  `body`           TEXT NOT NULL,
  `announcement_type` ENUM('general','exam','event','holiday','circular','deadline') DEFAULT 'general',
  `starts_at`      DATETIME NULL,
  `ends_at`        DATETIME NULL,
  `meta`           JSON NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `academic_events` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `title`          VARCHAR(200) NOT NULL,
  `event_type`     VARCHAR(60) NULL,
  `event_date`     DATE NOT NULL,
  `end_date`       DATE NULL,
  `description`    TEXT NULL,
  `meta`           JSON NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Finance / expenses (Admin)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `name`           VARCHAR(100) NOT NULL,
  `code`           VARCHAR(40) NULL,
  `meta`           JSON NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `expenses` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `department_id`  INT UNSIGNED NULL,
  `category_id`    INT UNSIGNED NULL,
  `category`       VARCHAR(100) NOT NULL,
  `title`          VARCHAR(200) NOT NULL,
  `amount`         DECIMAL(12,2) NOT NULL,
  `expense_date`   DATE NOT NULL,
  `vendor`         VARCHAR(150) NULL,
  `payment_mode`   VARCHAR(40) NULL,
  `added_by`       INT UNSIGNED NOT NULL,
  `receipt_url`    VARCHAR(255) NULL,
  `meta`           JSON NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_exp_inst` (`institution_id`, `expense_date`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `budgets` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `department_id`  INT UNSIGNED NULL,
  `fiscal_year`    VARCHAR(20) NOT NULL,
  `category`       VARCHAR(100) NULL,
  `allocated`      DECIMAL(12,2) NOT NULL,
  `meta`           JSON NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- AI usage logs + prompt templates (expandable AI modules)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_prompt_templates` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`         VARCHAR(64) NOT NULL UNIQUE,
  `module`       VARCHAR(64) NOT NULL,
  `name`         VARCHAR(120) NOT NULL,
  `system_prompt` LONGTEXT NOT NULL,
  `user_template` LONGTEXT NULL,
  `output_schema` JSON NULL,
  `model`        VARCHAR(80) DEFAULT 'gemini-2.0-flash',
  `version`      INT UNSIGNED DEFAULT 1,
  `is_active`    TINYINT(1) DEFAULT 1,
  `meta`         JSON NULL,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ai_generations` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NOT NULL,
  `module`         VARCHAR(64) NOT NULL,
  `prompt_code`    VARCHAR(64) NULL,
  `input_payload`  JSON NULL,
  `output_payload` JSON NULL,
  `model`          VARCHAR(80) NULL,
  `tokens_in`      INT UNSIGNED NULL,
  `tokens_out`     INT UNSIGNED NULL,
  `latency_ms`     INT UNSIGNED NULL,
  `status`         ENUM('success','error','partial') DEFAULT 'success',
  `error_message`  TEXT NULL,
  `ref_type`       VARCHAR(40) NULL,
  `ref_id`         INT UNSIGNED NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ai_user` (`user_id`),
  KEY `idx_ai_module` (`module`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Study assistant chats (student Ask AI)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_chats` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NOT NULL,
  `subject_id`     INT UNSIGNED NULL,
  `title`          VARCHAR(200) NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `chat_id`    INT UNSIGNED NOT NULL,
  `role`       ENUM('user','assistant','system') NOT NULL,
  `content`    LONGTEXT NOT NULL,
  `citations`  JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_chat_msgs` (`chat_id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Compliance / NAAC helpers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compliance_alerts` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NOT NULL,
  `department_id`  INT UNSIGNED NULL,
  `plan_id`        INT UNSIGNED NULL,
  `alert_type`     VARCHAR(60) NOT NULL,
  `severity`       ENUM('low','medium','high') DEFAULT 'medium',
  `message`        TEXT NOT NULL,
  `is_resolved`    TINYINT(1) DEFAULT 0,
  `meta`           JSON NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NULL,
  `user_id`        INT UNSIGNED NULL,
  `action`         VARCHAR(80) NOT NULL,
  `entity_type`    VARCHAR(60) NULL,
  `entity_id`      INT UNSIGNED NULL,
  `ip_address`     VARCHAR(45) NULL,
  `details`        JSON NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_act_inst` (`institution_id`, `created_at`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Settings key-value (global + per institution)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_settings` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `institution_id` INT UNSIGNED NULL,
  `setting_key`    VARCHAR(100) NOT NULL,
  `setting_value`  JSON NULL,
  UNIQUE KEY `uq_setting` (`institution_id`, `setting_key`)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
