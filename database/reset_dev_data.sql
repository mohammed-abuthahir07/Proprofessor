-- ============================================================
-- DESTRUCTIVE — LOCAL DEVELOPMENT DATA RESET ONLY
-- ============================================================
--
-- Canonical runner (inspects live schema, dry-run, transaction,
-- preserves College Admin by role, cleans uploads):
--
--   php database/reset_dev_data.php
--   php database/reset_dev_data.php --confirm-local-reset
--
-- This .sql file is a phpMyAdmin / mysql fallback. Prefer the PHP
-- script: it discovers runtime-created tables and foreign keys.
--
-- If you run this file directly:
--   1. Confirm you are on a LOCAL copy of `proprofessor`.
--   2. Confirm a College Admin (users.role = 'admin') exists.
--   3. Do NOT run this against a production host.
--
-- Preserves:
--   - users.role IN ('admin','superadmin') — entire auth row unchanged
--   - institutions linked to those users (including subscription_tier)
--   - feature_flags (global catalog)
--   - ai_prompt_templates (global AI catalog)
--   - institution_features for those institutions
--   - global app_settings (institution_id IS NULL)
--   - database schema / tables / indexes / foreign keys
--
-- ============================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

SET @ppai_db := DATABASE();
SET @ppai_admin_ok := (
  SELECT COUNT(*) FROM `users` WHERE `role` = 'admin'
);

DROP PROCEDURE IF EXISTS `sp_ppai_dev_reset_guard`;
DROP PROCEDURE IF EXISTS `sp_ppai_delete_if_exists`;
DROP PROCEDURE IF EXISTS `sp_ppai_ai_if_exists`;

DELIMITER //
CREATE PROCEDURE `sp_ppai_dev_reset_guard`()
BEGIN
  IF @ppai_db IS NULL OR @ppai_db <> 'proprofessor' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'REFUSED: reset_dev_data.sql only runs on database `proprofessor`.';
  END IF;
  IF @ppai_admin_ok < 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'REFUSED: no College Admin (users.role = admin) was found.';
  END IF;
END //
CREATE PROCEDURE `sp_ppai_delete_if_exists`(IN t VARCHAR(64))
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND TABLE_TYPE = 'BASE TABLE'
  ) THEN
    SET @ppai_q = CONCAT('DELETE FROM `', t, '`');
    PREPARE ppai_s FROM @ppai_q;
    EXECUTE ppai_s;
    DEALLOCATE PREPARE ppai_s;
  END IF;
END //
CREATE PROCEDURE `sp_ppai_ai_if_exists`(IN t VARCHAR(64))
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND TABLE_TYPE = 'BASE TABLE'
  ) THEN
    SET @ppai_q = CONCAT('ALTER TABLE `', t, '` AUTO_INCREMENT = 1');
    PREPARE ppai_s FROM @ppai_q;
    EXECUTE ppai_s;
    DEALLOCATE PREPARE ppai_s;
  END IF;
END //
DELIMITER ;

CALL `sp_ppai_dev_reset_guard`();

CALL `sp_ppai_delete_if_exists`('ai_chat_messages');
CALL `sp_ppai_delete_if_exists`('question_attempts');
CALL `sp_ppai_delete_if_exists`('assignment_submissions');
CALL `sp_ppai_delete_if_exists`('assignment_extension_requests');
CALL `sp_ppai_delete_if_exists`('attendance_records');
CALL `sp_ppai_delete_if_exists`('attendance_regularization_requests');
CALL `sp_ppai_delete_if_exists`('attendance_qr_tokens');
CALL `sp_ppai_delete_if_exists`('document_chunks');
CALL `sp_ppai_delete_if_exists`('questions');
CALL `sp_ppai_delete_if_exists`('course_plan_versions');
CALL `sp_ppai_delete_if_exists`('plan_units');
CALL `sp_ppai_delete_if_exists`('plan_reviews');
CALL `sp_ppai_delete_if_exists`('lesson_plans');
CALL `sp_ppai_delete_if_exists`('presentations');
CALL `sp_ppai_delete_if_exists`('exam_papers');
CALL `sp_ppai_delete_if_exists`('assignment_templates');
CALL `sp_ppai_delete_if_exists`('professor_announcements');
CALL `sp_ppai_delete_if_exists`('admin_hod_announcements');
CALL `sp_ppai_delete_if_exists`('professor_hod_messages');
CALL `sp_ppai_delete_if_exists`('question_banks');
CALL `sp_ppai_delete_if_exists`('assignments');
CALL `sp_ppai_delete_if_exists`('attendance_sessions');
CALL `sp_ppai_delete_if_exists`('internal_marks');
CALL `sp_ppai_delete_if_exists`('enrollments');
CALL `sp_ppai_delete_if_exists`('subject_assignments');
CALL `sp_ppai_delete_if_exists`('students_roster');
CALL `sp_ppai_delete_if_exists`('notifications');
CALL `sp_ppai_delete_if_exists`('password_resets');
CALL `sp_ppai_delete_if_exists`('activity_logs');
CALL `sp_ppai_delete_if_exists`('ai_chats');
CALL `sp_ppai_delete_if_exists`('ai_generations');
CALL `sp_ppai_delete_if_exists`('announcements');
CALL `sp_ppai_delete_if_exists`('academic_events');
CALL `sp_ppai_delete_if_exists`('documents');
CALL `sp_ppai_delete_if_exists`('expenses');
CALL `sp_ppai_delete_if_exists`('budgets');
CALL `sp_ppai_delete_if_exists`('expense_categories');
CALL `sp_ppai_delete_if_exists`('compliance_alerts');
CALL `sp_ppai_delete_if_exists`('course_plans');
CALL `sp_ppai_delete_if_exists`('marks_formulas');
CALL `sp_ppai_delete_if_exists`('subjects');
CALL `sp_ppai_delete_if_exists`('classes');
CALL `sp_ppai_delete_if_exists`('programs');

UPDATE `departments` SET `hod_user_id` = NULL;
CALL `sp_ppai_delete_if_exists`('departments');

DELETE FROM `app_settings` WHERE `institution_id` IS NOT NULL;

UPDATE `users`
SET `department_id` = NULL, `class_id` = NULL
WHERE `role` IN ('admin', 'superadmin')
  AND (`department_id` IS NOT NULL OR `class_id` IS NOT NULL);
DELETE FROM `users` WHERE `role` NOT IN ('admin', 'superadmin');

DELETE FROM `institution_features`
WHERE `institution_id` NOT IN (
  SELECT `institution_id` FROM (
    SELECT DISTINCT `institution_id` FROM `users` WHERE `role` IN ('admin', 'superadmin')
  ) AS preserved_inst
);
DELETE FROM `institutions`
WHERE `id` NOT IN (
  SELECT `institution_id` FROM (
    SELECT DISTINCT `institution_id` FROM `users` WHERE `role` IN ('admin', 'superadmin')
  ) AS preserved_inst
);

CALL `sp_ppai_ai_if_exists`('ai_chat_messages');
CALL `sp_ppai_ai_if_exists`('question_attempts');
CALL `sp_ppai_ai_if_exists`('assignment_submissions');
CALL `sp_ppai_ai_if_exists`('assignment_extension_requests');
CALL `sp_ppai_ai_if_exists`('attendance_records');
CALL `sp_ppai_ai_if_exists`('attendance_regularization_requests');
CALL `sp_ppai_ai_if_exists`('attendance_qr_tokens');
CALL `sp_ppai_ai_if_exists`('document_chunks');
CALL `sp_ppai_ai_if_exists`('questions');
CALL `sp_ppai_ai_if_exists`('course_plan_versions');
CALL `sp_ppai_ai_if_exists`('plan_units');
CALL `sp_ppai_ai_if_exists`('plan_reviews');
CALL `sp_ppai_ai_if_exists`('lesson_plans');
CALL `sp_ppai_ai_if_exists`('presentations');
CALL `sp_ppai_ai_if_exists`('exam_papers');
CALL `sp_ppai_ai_if_exists`('assignment_templates');
CALL `sp_ppai_ai_if_exists`('professor_announcements');
CALL `sp_ppai_ai_if_exists`('admin_hod_announcements');
CALL `sp_ppai_ai_if_exists`('professor_hod_messages');
CALL `sp_ppai_ai_if_exists`('question_banks');
CALL `sp_ppai_ai_if_exists`('assignments');
CALL `sp_ppai_ai_if_exists`('attendance_sessions');
CALL `sp_ppai_ai_if_exists`('internal_marks');
CALL `sp_ppai_ai_if_exists`('enrollments');
CALL `sp_ppai_ai_if_exists`('subject_assignments');
CALL `sp_ppai_ai_if_exists`('students_roster');
CALL `sp_ppai_ai_if_exists`('notifications');
CALL `sp_ppai_ai_if_exists`('password_resets');
CALL `sp_ppai_ai_if_exists`('activity_logs');
CALL `sp_ppai_ai_if_exists`('ai_chats');
CALL `sp_ppai_ai_if_exists`('ai_generations');
CALL `sp_ppai_ai_if_exists`('announcements');
CALL `sp_ppai_ai_if_exists`('academic_events');
CALL `sp_ppai_ai_if_exists`('documents');
CALL `sp_ppai_ai_if_exists`('expenses');
CALL `sp_ppai_ai_if_exists`('budgets');
CALL `sp_ppai_ai_if_exists`('expense_categories');
CALL `sp_ppai_ai_if_exists`('compliance_alerts');
CALL `sp_ppai_ai_if_exists`('course_plans');
CALL `sp_ppai_ai_if_exists`('marks_formulas');
CALL `sp_ppai_ai_if_exists`('subjects');
CALL `sp_ppai_ai_if_exists`('classes');
CALL `sp_ppai_ai_if_exists`('programs');
CALL `sp_ppai_ai_if_exists`('departments');

DROP PROCEDURE IF EXISTS `sp_ppai_dev_reset_guard`;
DROP PROCEDURE IF EXISTS `sp_ppai_delete_if_exists`;
DROP PROCEDURE IF EXISTS `sp_ppai_ai_if_exists`;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 1;
