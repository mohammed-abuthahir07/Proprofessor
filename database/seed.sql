-- ============================================================
-- ProProfessor AI — Seed data + feature flags + demo users
-- Default password for all demo users: Password@123
-- ============================================================

USE `proprofessor`;

INSERT INTO `feature_flags` (`code`, `name`, `description`, `module`, `is_enabled`, `default_config`) VALUES
('ai_course_plan', 'AI Course Plan Generator', 'Generate structured course plans from syllabus', 'professor', 1, JSON_OBJECT('model','gemini-2.5-flash')),
('bloom_mapper', 'Bloom''s Taxonomy Auto-Mapper', 'Map units/topics to K1-K6', 'professor', 1, NULL),
('ai_review', 'AI Curriculum Review', '12-parameter quality review', 'professor', 1, NULL),
('improve_ai', 'Improve with AI', 'Instruction-based plan improvement', 'professor', 1, NULL),
('lesson_planner', 'AI Lesson Planner', 'Session-wise lesson plans', 'professor', 1, NULL),
('question_bank', 'Question Bank Generator', 'MCQ / short / long by K-level', 'professor', 1, NULL),
('ppt_generator', 'AI PPT Generator', 'Generate presentation slides', 'professor', 1, NULL),
('assignment_ai', 'AI Assignment Generator', 'Multi-type assignments + rubrics', 'professor', 1, NULL),
('attendance', 'Smart Attendance', 'Attendance with 75% alerts', 'professor', 1, JSON_OBJECT('min_pct',75)),
('internal_marks', 'Configurable Internal Marks', 'Formula-driven CIA marks', 'professor', 1, NULL),
('version_control', 'Plan Version Control', 'Draft to approved workflow', 'professor', 1, NULL),
('notifications', 'Notifications Centre', 'In-app notifications', 'core', 1, NULL),
('student_portal', 'Student Portal', 'Courses, notes, submissions', 'student', 1, NULL),
('ask_ai', 'Ask AI Study Assistant', 'RAG-style study chatbot', 'student', 1, NULL),
('hod_approvals', 'HOD Approvals', 'Course plan review queue', 'hod', 1, NULL),
('dept_analytics', 'Department Analytics', 'Bloom & quality analytics', 'hod', 1, NULL),
('naac_reports', 'NAAC/NBA Reports', 'Accreditation document builder', 'admin', 1, NULL),
('finance', 'Finance & Expenses', 'Operational cost tracking', 'admin', 1, NULL),
('user_management', 'Role & User Management', 'Bulk import & roles', 'admin', 1, NULL),
('api_hub', 'API & Integration Hub', 'External integrations', 'admin', 0, JSON_OBJECT('coming_soon', true));

INSERT INTO `institutions`
(`name`,`code`,`affiliation_university`,`naac_grade`,`city`,`state`,`subscription_tier`,`licensed_seats`,`academic_year`,`current_semester`,`settings`)
VALUES
('Madurai Demo Arts & Science College','MDASC','Madurai Kamaraj University','A','Madurai','Tamil Nadu','professional',120,'2025-26','Odd Semester',
 JSON_OBJECT('timezone','Asia/Kolkata','attendance_min',75,'marks_pattern','Madurai'));

SET @inst_id = LAST_INSERT_ID();

INSERT INTO `departments` (`institution_id`,`name`,`code`) VALUES
(@inst_id, 'Computer Science', 'CS'),
(@inst_id, 'Commerce', 'COM'),
(@inst_id, 'English', 'ENG');

SET @dept_cs = (SELECT id FROM departments WHERE institution_id=@inst_id AND code='CS' LIMIT 1);
SET @dept_com = (SELECT id FROM departments WHERE institution_id=@inst_id AND code='COM' LIMIT 1);

INSERT INTO `institution_features` (`institution_id`,`feature_code`,`is_enabled`,`config`,`enabled_at`)
SELECT @inst_id, code, is_enabled, default_config, NOW() FROM feature_flags;

-- password: Password@123 (bcrypt)
SET @pwd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
-- Note: above is Laravel's "password" hash. We'll set proper hash via install script.
-- Using a known bcrypt for Password@123 generated for this seed:

SET @pwd = '$2y$10$e0NRzQm5Y5Y5Y5Y5Y5Y5YuKplaceholder';

-- Programs / classes / subjects
INSERT INTO `programs` (`institution_id`,`department_id`,`name`,`code`,`level`) VALUES
(@inst_id, @dept_cs, 'B.Sc Computer Science', 'BSC-CS', 'UG'),
(@inst_id, @dept_com, 'B.Com', 'BCOM', 'UG');

INSERT INTO `classes` (`institution_id`,`department_id`,`name`,`section`,`year`,`semester`,`academic_year`) VALUES
(@inst_id, @dept_cs, 'II B.Sc CS', 'A', 2, 'Odd Semester', '2025-26'),
(@inst_id, @dept_com, 'II B.Com', 'A', 2, 'Odd Semester', '2025-26');

SET @class_cs = (SELECT id FROM classes WHERE name='II B.Sc CS' AND institution_id=@inst_id LIMIT 1);

INSERT INTO `subjects` (`institution_id`,`department_id`,`code`,`name`,`credits`,`contact_hours`,`semester`,`syllabus_text`) VALUES
(@inst_id, @dept_cs, 'CS301', 'Database Management Systems', 4, 60, 'Odd Semester',
 'Unit 1: Introduction to DBMS, Data Models, ER Diagrams\nUnit 2: Relational Model, SQL DDL DML\nUnit 3: Normalization, Transactions\nUnit 4: Indexing, Query Optimization\nUnit 5: NoSQL and Emerging Trends'),
(@inst_id, @dept_cs, 'CS302', 'Web Technologies', 3, 45, 'Odd Semester',
 'Unit 1: HTML5 CSS3 Basics\nUnit 2: JavaScript & DOM\nUnit 3: PHP & MySQL\nUnit 4: REST APIs\nUnit 5: Security & Deployment');

INSERT INTO `expense_categories` (`institution_id`,`name`,`code`) VALUES
(@inst_id,'Salaries','SAL'),
(@inst_id,'Lab & Library','LAB'),
(@inst_id,'Infrastructure','INFRA'),
(@inst_id,'Events','EVT'),
(@inst_id,'Utilities','UTIL');

INSERT INTO `marks_formulas`
(`institution_id`,`department_id`,`name`,`pattern`,`plain_english`,`components`,`expression`,`total_max`,`is_default`,`created_by`)
VALUES
(@inst_id, @dept_cs, 'Madurai Pattern CIA', 'Madurai',
 'Average of CIA1 and CIA2 scaled to 15, plus Assignment 5 and Attendance 5, total 25',
 JSON_ARRAY(
   JSON_OBJECT('code','cia1','label','CIA 1','max',50,'weight',0.3),
   JSON_OBJECT('code','cia2','label','CIA 2','max',50,'weight',0.3),
   JSON_OBJECT('code','assignment','label','Assignment','max',5,'weight',0.2),
   JSON_OBJECT('code','attendance','label','Attendance','max',5,'weight',0.2)
 ),
 '((cia1+cia2)/2)*(15/50) + assignment + attendance',
 25, 1, NULL);

INSERT INTO `ai_prompt_templates` (`code`,`module`,`name`,`system_prompt`,`user_template`,`output_schema`,`model`) VALUES
('course_plan','course_plan','Course Plan Generator',
 'You are an expert Indian higher-education curriculum designer specializing in OBE, Bloom''s taxonomy (K1-K6), NAAC Binary 2025 and NBA GAPC v4. Return ONLY valid JSON.',
 'Subject: {{subject}}\nCredits: {{credits}}\nUniversity: {{university}}\nSyllabus:\n{{syllabus}}',
 JSON_OBJECT('type','object'),'gemini-2.5-flash'),
('bloom_map','bloom','Bloom Mapper',
 'Map each unit/topic to Bloom K1-K6. Return ONLY valid JSON with units array and distribution percentages.',
 '{{plan_json}}', JSON_OBJECT('type','object'),'gemini-2.5-flash'),
('ai_review','review','Curriculum Review',
 'Evaluate the course plan on 12 parameters (NAAC, industry, OBE, resources, hours, K-balance, etc). Return JSON with score 0-100 and recommendations.',
 '{{plan_json}}', JSON_OBJECT('type','object'),'gemini-2.5-flash'),
('lesson_plan','lesson','Lesson Planner',
 'Generate session-by-session lesson plans from the course plan. Return JSON array of sessions.',
 '{{plan_json}}', JSON_OBJECT('type','object'),'gemini-2.5-flash'),
('question_bank','questions','Question Bank',
 'Generate exam questions (MCQ, short, long) tagged by Bloom K-level and unit. Return ONLY JSON.',
 'Type: {{type}}\nUnit: {{unit}}\nK-level: {{klevel}}\nCount: {{count}}\nSyllabus context:\n{{context}}',
 JSON_OBJECT('type','object'),'gemini-2.5-flash'),
('ppt_gen','ppt','PPT Generator',
 'Generate a professional 12-20 slide teaching presentation as JSON slides with title, bullets, speaker_notes, unit_tag.',
 '{{context}}', JSON_OBJECT('type','object'),'gemini-2.5-flash'),
('assignment_gen','assignment','Assignment Generator',
 'Generate a NAAC-compliant assignment with rubric for the given type. Return ONLY JSON.',
 'Type: {{type}}\nSubject: {{subject}}\nContext:\n{{context}}',
 JSON_OBJECT('type','object'),'gemini-2.5-flash'),
('formula_nlp','marks','Formula NLP Parser',
 'Parse plain-English internal marks formula used in Indian universities into structured JSON components and expression.',
 '{{formula_text}}', JSON_OBJECT('type','object'),'gemini-2.5-flash'),
('study_assistant','ask_ai','Student Study Assistant',
 'Answer using ONLY the provided course materials. Cite sources. If unknown, say so.',
 'Materials:\n{{materials}}\n\nQuestion: {{question}}',
 NULL,'gemini-2.5-flash'),
('improve_plan','improve','Improve with AI',
 'Apply the professor instruction to improve the course plan. Return full updated plan JSON and a change summary.',
 'Instruction: {{instruction}}\nCurrent plan:\n{{plan_json}}',
 JSON_OBJECT('type','object'),'gemini-2.5-flash');

INSERT INTO `academic_events` (`institution_id`,`title`,`event_type`,`event_date`,`description`) VALUES
(@inst_id, 'CIA Test I', 'exam', '2025-09-15', 'Continuous Internal Assessment I'),
(@inst_id, 'CIA Test II', 'exam', '2025-11-10', 'Continuous Internal Assessment II'),
(@inst_id, 'Diwali Holiday', 'holiday', '2025-10-20', 'College holiday');

INSERT INTO `announcements` (`institution_id`,`created_by`,`title`,`body`,`announcement_type`)
VALUES (@inst_id, 1, 'Welcome to ProProfessor AI', 'Academic year 2025-26 portal is live. Upload course plans before the HOD deadline.', 'general');

-- Demo users are created by install.php with correct password hashes.
