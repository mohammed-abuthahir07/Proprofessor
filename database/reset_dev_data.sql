-- ============================================================
-- DESTRUCTIVE — LOCAL DEVELOPMENT DATA RESET ONLY
-- ============================================================
--
-- This script DELETES application/demo data from the local
-- `proprofessor` database. It is NOT for production.
--
-- Preferred runner (enforces extra host/env guards):
--   php database/reset_dev_data.php --confirm-local-reset
--
-- If you run this file directly (mysql / phpMyAdmin):
--   1. Confirm you are on a LOCAL copy of `proprofessor`.
--   2. Confirm users.id = 1 is admin@proprofessor.local.
--   3. Do NOT run this against a production host.
--
-- Preserves:
--   - users.id = 1 (College Admin) — entire row unchanged
--   - institutions.id = 1 (minimum required FK/dashboard shell)
--   - feature_flags (global catalog)
--   - ai_prompt_templates (global AI catalog)
--   - institution_features for institution 1 (module toggles)
--   - global app_settings (institution_id IS NULL)
--   - database schema / tables / indexes / foreign keys
--
-- ============================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- Refuse unless this session is on the local development database.
SET @ppai_db := DATABASE();
SET @ppai_admin_ok := (
  SELECT COUNT(*) FROM `users`
  WHERE `id` = 1
    AND `email` = 'admin@proprofessor.local'
    AND `role` = 'admin'
    AND `full_name` = 'College Admin'
);

DROP PROCEDURE IF EXISTS `sp_ppai_dev_reset_guard`;
DELIMITER //
CREATE PROCEDURE `sp_ppai_dev_reset_guard`()
BEGIN
  IF @ppai_db IS NULL OR @ppai_db <> 'proprofessor' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'REFUSED: reset_dev_data.sql only runs on database `proprofessor`.';
  END IF;
  IF @ppai_admin_ok <> 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'REFUSED: protected Admin users.id=1 / admin@proprofessor.local was not found.';
  END IF;
END //
DELIMITER ;
CALL `sp_ppai_dev_reset_guard`();
DROP PROCEDURE IF EXISTS `sp_ppai_dev_reset_guard`;

-- ------------------------------------------------------------
-- 1) Leaf / child application data
-- ------------------------------------------------------------
DELETE FROM `ai_chat_messages`;
DELETE FROM `assignment_submissions`;
DELETE FROM `attendance_records`;
DELETE FROM `document_chunks`;
DELETE FROM `questions`;
DELETE FROM `course_plan_versions`;
DELETE FROM `plan_units`;
DELETE FROM `plan_reviews`;
DELETE FROM `lesson_plans`;
DELETE FROM `presentations`;
DELETE FROM `question_banks`;
DELETE FROM `assignments`;
DELETE FROM `attendance_sessions`;
DELETE FROM `internal_marks`;
DELETE FROM `enrollments`;
DELETE FROM `subject_assignments`;
DELETE FROM `students_roster`;
DELETE FROM `notifications`;
DELETE FROM `password_resets`;
DELETE FROM `activity_logs`;
DELETE FROM `ai_chats`;
DELETE FROM `ai_generations`;
DELETE FROM `announcements`;
DELETE FROM `academic_events`;
DELETE FROM `documents`;
DELETE FROM `expenses`;
DELETE FROM `budgets`;
DELETE FROM `expense_categories`;
DELETE FROM `compliance_alerts`;
DELETE FROM `course_plans`;
DELETE FROM `marks_formulas`;

-- ------------------------------------------------------------
-- 2) Academic structure (demo departments / classes / subjects)
-- ------------------------------------------------------------
DELETE FROM `subjects`;
DELETE FROM `classes`;
DELETE FROM `programs`;
DELETE FROM `departments`;

-- Per-institution settings only (keep global rows where institution_id IS NULL)
DELETE FROM `app_settings` WHERE `institution_id` IS NOT NULL;

-- ------------------------------------------------------------
-- 3) Users — keep ONLY Admin id=1 (row is not updated)
-- ------------------------------------------------------------
DELETE FROM `users` WHERE `id` <> 1;

-- ------------------------------------------------------------
-- 4) Minimal institution shell required by users.institution_id
--    and the Admin dashboard. Demo college identity is cleared.
--    id remains 1 so Admin FK stays valid.
-- ------------------------------------------------------------
UPDATE `institutions`
SET
  `name` = 'Institution',
  `code` = NULL,
  `affiliation_university` = NULL,
  `naac_grade` = NULL,
  `nba_status` = NULL,
  `address` = NULL,
  `city` = NULL,
  `state` = NULL,
  `pincode` = NULL,
  `phone` = NULL,
  `email` = NULL,
  `logo_url` = NULL,
  `subscription_tier` = 'trial',
  `licensed_seats` = 60,
  `academic_year` = NULL,
  `current_semester` = NULL,
  `settings` = '{"attendance_min": 75}',
  `is_active` = 1
WHERE `id` = 1;

-- ------------------------------------------------------------
-- 5) Reset AUTO_INCREMENT (Admin remains id=1)
-- ------------------------------------------------------------
ALTER TABLE `ai_chat_messages` AUTO_INCREMENT = 1;
ALTER TABLE `assignment_submissions` AUTO_INCREMENT = 1;
ALTER TABLE `attendance_records` AUTO_INCREMENT = 1;
ALTER TABLE `document_chunks` AUTO_INCREMENT = 1;
ALTER TABLE `questions` AUTO_INCREMENT = 1;
ALTER TABLE `course_plan_versions` AUTO_INCREMENT = 1;
ALTER TABLE `plan_units` AUTO_INCREMENT = 1;
ALTER TABLE `plan_reviews` AUTO_INCREMENT = 1;
ALTER TABLE `lesson_plans` AUTO_INCREMENT = 1;
ALTER TABLE `presentations` AUTO_INCREMENT = 1;
ALTER TABLE `question_banks` AUTO_INCREMENT = 1;
ALTER TABLE `assignments` AUTO_INCREMENT = 1;
ALTER TABLE `attendance_sessions` AUTO_INCREMENT = 1;
ALTER TABLE `internal_marks` AUTO_INCREMENT = 1;
ALTER TABLE `enrollments` AUTO_INCREMENT = 1;
ALTER TABLE `subject_assignments` AUTO_INCREMENT = 1;
ALTER TABLE `students_roster` AUTO_INCREMENT = 1;
ALTER TABLE `notifications` AUTO_INCREMENT = 1;
ALTER TABLE `password_resets` AUTO_INCREMENT = 1;
ALTER TABLE `activity_logs` AUTO_INCREMENT = 1;
ALTER TABLE `ai_chats` AUTO_INCREMENT = 1;
ALTER TABLE `ai_generations` AUTO_INCREMENT = 1;
ALTER TABLE `announcements` AUTO_INCREMENT = 1;
ALTER TABLE `academic_events` AUTO_INCREMENT = 1;
ALTER TABLE `documents` AUTO_INCREMENT = 1;
ALTER TABLE `expenses` AUTO_INCREMENT = 1;
ALTER TABLE `budgets` AUTO_INCREMENT = 1;
ALTER TABLE `expense_categories` AUTO_INCREMENT = 1;
ALTER TABLE `compliance_alerts` AUTO_INCREMENT = 1;
ALTER TABLE `course_plans` AUTO_INCREMENT = 1;
ALTER TABLE `marks_formulas` AUTO_INCREMENT = 1;
ALTER TABLE `subjects` AUTO_INCREMENT = 1;
ALTER TABLE `classes` AUTO_INCREMENT = 1;
ALTER TABLE `programs` AUTO_INCREMENT = 1;
ALTER TABLE `departments` AUTO_INCREMENT = 1;
ALTER TABLE `app_settings` AUTO_INCREMENT = 1;
ALTER TABLE `users` AUTO_INCREMENT = 2;

-- ------------------------------------------------------------
-- 6) Restore foreign-key checks
-- ------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 1;
