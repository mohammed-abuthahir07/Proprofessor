# ProProfessor AI

**India-focused academic operating system** — institution management, HOD governance, professor workflows, and student portal with Gemini AI assistance.

> **Technology:** This is a **PHP + MySQL** application. It is **not** Node.js, Express, React, MongoDB, or npm-based. There is no `package.json` in this repository.

---

## What this application is

ProProfessor AI helps a college manage:

- Institution structure (departments, classes, users)
- Department-level courses and professor assignments (HOD)
- Course plans with HOD approval
- Attendance, internal marks, assignments, notes/PPT
- Student portal scoped to each student's class and enrolled courses
- Optional Gemini AI for course plans, assignments, lesson plans, PPT, and student Q&A

Data is isolated per **institution** (`institution_id`). HOD and professor workflows further isolate by **department** and **assigned class/course**.

---

## Architecture (verified from codebase)

### Type: Custom PHP MVC (not Laravel / CodeIgniter)

| Layer | Location | Role |
|-------|----------|------|
| Front controller | `index.php` | All pretty URLs enter here |
| Router | `app/Core/Router.php`, `routes/web.php` | Maps paths to controllers |
| App bootstrap | `app/bootstrap.php`, `includes/bootstrap.php` | Autoload, config, session |
| Controllers | `app/Controllers/` | Admin, Auth, HOD, Professor, Student, Api, Legacy |
| Models | `app/Models/` | PDO wrappers (`User`, `Subject`, `CoursePlan`, …) |
| Views | `app/Views/` | PHP templates + `layouts/app.php` |
| Legacy pages | `professor/`, `student/`, `hod/`, `api/ai.php` | Feature modules via `LegacyController` |
| Shared logic | `includes/helpers.php` | Authorization, enrollments, course visibility |
| Auth | `includes/Auth.php` | PHP **sessions** + `password_verify()` (bcrypt) |
| Database | `includes/Database.php` | PDO singleton to MySQL |
| Config | `config/config.php`, optional `config/config.local.php` | DB, Gemini, `base_url` |
| Navigation | `app/Services/NavService.php` | Role-based sidebar |
| Frontend assets | `assets/css/app.css`, `assets/js/app.js` | Served with PHP layouts |
| Schema | `database/schema.sql` | MySQL 8+ / MariaDB 10.5+ |

**Request flow:**

```
Browser → .htaccess → index.php → App::run() → routes/web.php
  → Controller (MVC) OR LegacyController → require professor|student|hod/*.php
  → includes/layout.php or app/Views/layouts/app.php
```

**Hybrid pattern:** Admin and role dashboards use full MVC. Most academic modules remain legacy PHP scripts whitelisted in `LegacyController`.

---

## Project structure

```
professor/
├── index.php                 # Front controller
├── .htaccess                 # Rewrite to index.php
├── install.php               # Web installer (schema + basic users)
├── routes/web.php            # Route definitions
├── app/
│   ├── Core/                 # App, Router, Controller, Model, View
│   ├── Controllers/          # Admin, Auth, Hod, Professor, Student, Api, Legacy
│   ├── Models/
│   ├── Services/             # NavService
│   └── Views/                # Role dashboards + admin views
├── includes/                 # Auth, Database, helpers, layout, Icons
├── config/
├── database/                 # schema.sql, seed.sql, reset/seed CLI scripts
├── assets/css/, assets/js/
├── professor/                # Legacy professor modules
├── student/                  # Legacy student modules
├── hod/                      # Legacy HOD modules
└── api/ai.php                # AI endpoints (also routed via AiController)
```

---

## Database (MySQL)

**Database name (local default):** `proprofessor`  
**Schema file:** `database/schema.sql`

### Core academic tables

| Table | Purpose |
|-------|---------|
| `institutions` | Tenant college; `academic_year`, `current_semester`, settings JSON |
| `departments` | Dept per institution; optional `hod_user_id` |
| `programs` | UG/PG program catalog (optional; classes often use `meta` instead) |
| `classes` | Academic group: `department_id`, `year` (1–4), `section`, `meta.level` (UG/PG) |
| `users` | All roles; students have `class_id`, `register_no` |
| `subjects` | Department courses (`code`, `name`, credits, syllabus) |
| `subject_assignments` | HOD assigns professor + class + academic year |
| `enrollments` | Student ↔ subject ↔ class (status, academic_year) |
| `course_plans` | AI/manual plans; status workflow |
| `course_plan_versions`, `plan_units`, `plan_reviews` | Versioning and HOD review |
| `attendance_sessions`, `attendance_records` | Class + subject attendance |
| `marks_formulas`, `internal_marks` | Configurable CIA formulas |
| `assignments`, `assignment_submissions` | Class-scoped assignments |
| `documents`, `presentations` | Notes and PPT content |
| `students_roster` | Roster mirror for attendance import |
| `announcements`, `academic_events`, `notifications` | Comms and calendar |
| `ai_generations`, `ai_chats`, `ai_chat_messages` | AI audit trail |

**Roles in `users.role`:** `admin`, `superadmin`, `hod`, `professor`, `student`

There is **no separate HOD/professor/student profile table** — role fields live on `users`.

---

## Role hierarchy (implemented)

```
Institution (users.institution_id)
  └── Department (users.department_id for HOD / professor / student)
        ├── HOD — one department per account
        ├── Professors — department account; access via subject_assignments
        └── Students — department + class_id (year/section/program via classes row)
```

| Role | Dashboard route | Scope |
|------|-----------------|-------|
| Admin / superadmin | `/admin/dashboard` | Whole institution |
| HOD | `/hod/dashboard` | Own department only |
| Professor | `/professor/dashboard` | HOD-assigned courses/classes only |
| Student | `/student/dashboard` | Own class + enrollments only |

There is **no Platform Admin UI** in this PHP codebase (single-institution admin model).

---

## Routes reference

Paths are relative to app base (e.g. `http://localhost/professor/admin/dashboard`).

### Authentication

| Method | Route | Handler |
|--------|-------|---------|
| GET | `/login` | Login form |
| POST | `/login` | Session login |
| GET | `/logout` | Logout |

### Admin (MVC)

| Route | Module |
|-------|--------|
| `/admin/dashboard` | Dashboard |
| `/admin/institution` | Institution, departments, **classes** (not courses) |
| `/admin/users` | HOD, professors, students, CSV import |
| `/admin/formulas` | Internal marks formulas |
| `/admin/features` | Feature flags |
| `/admin/finance` | Expenses |
| `/admin/naac` | NAAC builder |
| `/admin/analytics` | Institution analytics |
| `/admin/billing` | Subscription seats |
| `/admin/notifications` | Notifications |

### HOD

| Route | Module |
|-------|--------|
| `/hod/dashboard` | MVC dashboard |
| `/hod/approvals` | Course plan queue |
| `/hod/faculty` | Faculty list + department circulars |
| `/hod/students` | Department student roster (filters) |
| `/hod/subjects` | **Courses** — create subjects, assign professors |
| `/hod/analytics` | Department analytics |
| `/hod/compliance` | Compliance alerts |
| `/hod/timeline` | Timeline |
| `/hod/reports` | NAAC reports |
| `/hod/notifications` | Notifications |
| GET `/api/hod/students` | JSON student list (dept-isolated) |

### Professor (legacy via `/professor/{page}`)

`generate-plan`, `plans`, `plan-view`, `lessons`, `questions`, `ppt`, `ppt-view`, `ppt-download`, `assignments`, `attendance`, `marks`, `settings`

### Student (legacy via `/student/{page}`)

`courses`, `notes`, `assignments`, `attendance`, `marks`, `calendar`, `ask-ai`

### API

| Method | Route | Purpose |
|--------|-------|---------|
| POST | `/api/ai` | Gemini AI (course plan, assignment, lesson, PPT, etc.) |

---

## Admin functionality

**Implemented:** Institution profile, departments, classes (UG/PG via `classes.meta`, year 1–4, section), user CRUD, feature flags, marks formulas, finance, NAAC, analytics, billing, notifications.

**Admin does NOT create department courses/subjects** — verified in `InstitutionController` (no `add_subject` action) and `app/Views/admin/institution.php` (note points to HOD → Courses).

**Programs / years:** Modeled via `classes.year` and `classes.meta.level` (UG/PG), not separate admin “year management” screens.

---

## HOD functionality

Each HOD has `users.department_id` set at account creation (Admin → Users).

| Feature | Status | Route / code |
|---------|--------|----------------|
| Dashboard | ✅ | `/hod/dashboard` |
| Faculty list + circulars | ✅ | `/hod/faculty` → `announcements` |
| Department students | ✅ | `/hod/students`, `User::studentsForDepartment()` |
| Year/section/class filters | ✅ | Query params on students page |
| **Create courses** | ✅ | `/hod/subjects` → `hod_save_subject()` |
| **Assign professor + class** | ✅ | `hod_assign_professor_subject()` → `subject_assignments` + auto `enrollments` |
| Course plan approve/reject/return | ✅ | `/hod/approvals` |
| HOD feedback on plans | ✅ | `plan_reviews`, `includes/HodFeedback.php` |
| Department analytics | ✅ | `/hod/analytics` |
| Compliance / timeline / reports | ✅ | Legacy HOD pages |
| Manage professor accounts | ❌ | Professors created by Admin only |

**Isolation:** HOD SQL filters use session `department_id` + `institution_id`. Cross-department URL parameters are ignored or rejected (`hod/students.php`, `StudentsController`, `hod_save_subject()`).

---

## Course and course-plan workflow

### Intended and implemented flow

```
1. Admin creates department, classes, HOD, professors, students (class_id)
2. HOD → /hod/subjects:
     - Create subject (subjects row)
     - Assign professor + class (subject_assignments)
     - Students in class auto-enrolled (enrollments)
3. Professor sees only assigned pairs (professor_manageable_classes, professor_subjects)
4. Professor → /professor/generate-plan → submit via /professor/plans
5. HOD → /hod/approvals → approve | reject | return (returned status)
6. Professor manages attendance, marks, assignments for assigned course+class only
```

**Course plan statuses (`course_plans.status`):** `draft`, `submitted`, `under_review`, `approved`, `returned`

Professors **cannot** create subjects (`save_professor_subject()` throws; UI removed from professor pages).

---

## Class and section model

Stored in **`classes`**:

| Field | Example |
|-------|---------|
| `department_id` | CSE |
| `meta.level` | `"UG"` or `"PG"` (JSON) |
| `year` | `1` … `4` |
| `section` | `"A"`, `"B"` |
| `name` | Display label |

Students link via **`users.class_id`**.

Example:

```
CSE → UG → Year 1 → Section A → Mohammed, Ananya
CSE → UG → Year 1 → Section B → Arjun
```

Courses attach to a specific class through **`subject_assignments.class_id`**.

---

## Professor functionality

| Feature | Status | Notes |
|---------|--------|-------|
| View assigned courses/classes | ✅ | From `subject_assignments` only |
| AI course plan | ✅ | `/professor/generate-plan`, `POST /api/ai` |
| Submit / view plan status | ✅ | `/professor/plans`, `/professor/plan-view` |
| Lesson planner | ✅ | `/professor/lessons` |
| Question bank | ✅ | `/professor/questions` |
| PPT generator | ✅ | `/professor/ppt` |
| Attendance | ✅ | `/professor/attendance` — class + subject; CSV/Excel import |
| Internal marks | ✅ | `/professor/marks` — uses `marks_formulas` |
| Assignments | ✅ | `/professor/assignments` — AI generate + grade |
| Notes/PPT materials | ✅ | Via `documents`, `presentations` (linked to plans/subjects) |
| Notifications | ✅ | `/professor/notifications` |
| Academic calendar (own page) | ❌ | No `/professor/calendar` |
| Send announcements | ⚠️ | HOD circulars to faculty; professor receives notifications |

**Authorization (server-side):** `professor_can_manage_class()`, `professor_can_manage_subject()` in `includes/helpers.php`; enforced on attendance/marks POST and `api/ai.php`.

---

## Student functionality

| Feature | Status | Route |
|---------|--------|-------|
| My courses | ✅ | `/student/courses` — `courses_for_student()` |
| Attendance | ✅ | `/student/attendance` — own class + register_no |
| Internal marks | ✅ | `/student/marks` |
| Assignments | ✅ | `/student/assignments` — same `class_id` |
| Notes & PPT | ✅ | `/student/notes` |
| Academic calendar | ✅ | `/student/calendar` — `academic_events` + dept announcements |
| Ask AI | ✅ | `/student/ask-ai` — enrollment-gated |
| Notifications | ✅ | `/student/notifications` |

**Visibility rules (backend):**

- `student_class_id()` from `users.class_id`
- Courses: active `enrollments` matching current class + institution `academic_year` when set
- Assignments: `assignments_visible_to_student()` filters by `class_id`
- No access to other sections, years, or departments

---

## Department and institution isolation

| Actor | Enforcement |
|-------|-------------|
| All roles | `institution_id` on queries |
| HOD | `department_id` from session; helpers `hod_department_id()`, `User::studentsForDepartment()` |
| Professor | `subject_assignments` + `professor_can_manage_subject()` |
| Student | `class_id` + enrollment checks |

Isolation is enforced in **PHP/SQL**, not only in the UI.

---

## Academic year and progression

| Aspect | Status |
|--------|--------|
| Institution `academic_year` / semester | ✅ Set in Admin → Institution |
| Enrollments store `academic_year` | ✅ Set on HOD assignment |
| Student current courses filter by year | ✅ `courses_for_student()` |
| Admin moves student to new class | ✅ Admin → Users (`class_id` update) |
| Automatic bulk promotion (1st→2nd year) | ❌ Not implemented |
| Historical attendance/marks deleted on promotion | ❌ **Not deleted** — rows remain tied to old `class_id` |
| `academic_year` on attendance/marks tables | ❌ Not in schema; history keyed by `class_id` |

When a student’s `class_id` changes, old enrollments no longer match current course queries; HOD must assign new-year courses for the new class.

---

## Formula configuration

**Route:** `/admin/formulas` (`FormulaController`, `marks_formulas` table)

Each formula has:

- `components` (JSON) — e.g. CIA 1, CIA 2, Assignment, Attendance with max marks
- `expression` — evaluated in `/professor/marks`
- `plain_english` — human description

**E2E seed example** (`database/seed_e2e_test_data.php`):

| Field | Value |
|-------|-------|
| Plain English | Average of CIA 1 and CIA 2 scaled to 15, plus assignment and attendance to 25. |
| Expression | `((cia1+cia2)/2)*(15/50)+assignment+attendance` |
| CIA core (conceptual) | `(cia1 + cia2) / 2` then scaled |

**Professor UI fallback** when no DB formula exists: `((cia1+cia2)/2)*(25/50)` with CIA 1 + CIA 2 components only.

---

## Quick start (XAMPP)

1. Apache + MySQL running (XAMPP).
2. Create DB or run `http://localhost/professor/install.php`.
3. Configure `config/config.php` (or `config.local.php`) — host, port, database `proprofessor`.
4. Open `http://localhost/professor/login`.
5. Optional: set `GEMINI_API_KEY` in config or environment for live AI (demo mode works without key).

Enable Apache `mod_rewrite` and `AllowOverride All` for `.htaccess`.

### CLI database tools

```bash
# Wipe demo data — keeps Admin (id=1) only
php database/reset_dev_data.php --confirm-local-reset

# Full E2E academic demo dataset
php database/seed_e2e_test_data.php --confirm-local-rese
```

---

## Credentials

### Admin (always present after install)

| Email | Password | Notes |
|-------|----------|-------|
| `admin@proprofessor.local` | `Password@123` | Created by `install.php`; preserved by reset script |

### Demo accounts (only after E2E seed)

**🔍 Verified against live database (2026-08-22):** Only Admin exists until seed is run.  
After `php database/seed_e2e_test_data.php --confirm-local-reset`:

**Password for all seeded demo users:** `Test@12345`

| Role | Email | Seed notes |
|------|-------|------------|
| CSE HOD | `csehod@test.com` | CSE department |
| Professor — DBMS | `arun.kumar@test.com` | CS301 → CSE UG Year 1 Sec A |
| Professor — OS | `priya.kumar@test.com` | CS302 → CSE UG Year 1 Sec A |
| Professor — Networks | `rahul.kumar@test.com` | CS303 → CSE UG Year 1 Sec A |
| Professor — Java | `divya.kumar@test.com` | CS304 → CSE UG Year 1 Sec A |
| Professor — SE | `karthik.kumar@test.com` | CS305 → CSE UG Year 1 Sec A |
#	Professor Name	Email	Suggested Subject
1	Vikram Raj	|| `vikram.raj@test.com`	Data Structures
2	Meena Krishnan	|| `meena.krishnan@test.com`	Computer Organization
3	Suresh Babu ||	`suresh.babu@test.com`	Web Technologies
4	Nithya Devi	|| `nithya.devi@test.com`	Artificial Intelligence
5	Vignesh Kumar ||	`vignesh.kumar@test.com`	Machine Learning
6	Harish Anand ||	`harish.anand@test.com`	Cloud Computing
7	Keerthana || R	`keerthana.r@test.com`	Cyber Security

| Student — 1st Year B | `mohammed@test.com` | Register 224026 |
| Student — 1st Year A | `Ayyanar@test.com` | Register 224027 |
| Student — 1st Year C | `sahana@gmail.com` | Register 224113 |

| Student — 2nd Year A | `ananya@test.com` | Register CSE24002 |
| Student — 2nd Year B | `arjun@test.com` | Register CSE24011 |
| Student - 2nd Year C | `mani@test.com` | Register CSE230012
| Student - 2nd Year C | `manikam@test.com` | Register CSE230012

| Student — 3nd Year A | `naveen@test.com` | Register CSE24012 |
| Student — 3nd Year B | `madesh@test.com` | Register CSE24111 |
| Student - 3nd Year C | `kishore@test.com` | Register CSE230112

| Student — 4nd Year A | `sandhosini@test.com` | Register CSE2222 |
| Student — 4nd Year B | `saairuba@test.com` | Register CSE1111 |
| Student - 4nd Year C | `navya@test.com` | Register CSE2333

Password@123

Seed also creates ECE, EEE, IT, MECH HODs/professors/students — see script output.

### CSE subjects (after E2E seed only)

| Code | Name |
|------|------|
| CS301 | Database Management Systems |
| CS302 | Operating Systems |
| CS303 | Computer Networks |
| CS304 | Java Programming |
| CS305 | Software Engineering |

---

## Manual test flow (PHP application)

1. Login as **Admin** → create CSE department, classes (UG, year, section), HOD, professors, students with `class_id`.
2. Login as **CSE HOD** → `/hod/subjects` → create courses → assign professor + class.
3. Login as **Professor** → confirm only assigned course/class → generate & submit course plan.
4. Login as **HOD** → `/hod/approvals` → approve or reject with feedback.
5. Login as **Professor** → attendance, marks, assignments for that class/course.
6. Login as **Student** (Section A) → verify courses, attendance, marks, assignments match **own class only**; Section B student must not see Section A assignments.
7. (Optional) Admin changes student to next-year class → HOD assigns new courses → student course list updates; old marks/attendance remain in DB for old class.

---

## Deployment notes

1. Upload project to web root (e.g. `public_html/demo/professor`).
2. Copy `config/config.local.php.example` → `config/config.local.php` with production MySQL credentials.
3. Run `install.php` once, then remove or rename it.
4. `base_url` defaults to **auto** (detects subdirectory path).

---

## AI integration

- **Endpoint:** `POST /api/ai` (routes to `AiController` → `api/ai.php` logic)
- **Config:** `config/config.php` → `gemini.api_key`, `gemini.model`
- **Without API key:** Demo JSON responses for course plans and related modules
- **Modules:** `course_plan`, `assignment`, `lesson`, `questions`, `ppt`, `bloom`, `review`, `ask_ai`, etc.

---

# Current Implementation Status

| Module | Status | Notes |
|--------|--------|-------|
| Admin | ✅ Implemented | Institution, users, classes, formulas, features, finance, NAAC, analytics |
| HOD | ✅ Implemented | Approvals, faculty, students, **courses**, analytics, compliance, reports |
| Professor | ✅ Implemented | Assigned-course-only academic + AI tools |
| Student | ✅ Implemented | Class-scoped portal |
| Courses (HOD-owned) | ✅ Implemented | `/hod/subjects`; Admin does not create subjects |
| Course plans | ✅ Implemented | AI generate, version snapshots, HOD workflow |
| Attendance | ✅ Implemented | Class + subject; roster import; no academic_year column |
| Internal marks | ✅ Implemented | Configurable formulas + professor entry |
| Assignments | ✅ Implemented | Class-scoped publish and submission |
| Notes/PPT | ✅ Implemented | `documents`, `presentations` |
| Academic calendar | ✅ Implemented | Student `/student/calendar`; `academic_events` |
| Formula configuration | ✅ Implemented | `/admin/formulas` |
| Department isolation | ✅ Implemented | PHP helpers + SQL filters |
| Academic year progression | ⚠️ Partially implemented | Manual class change + enrollment year filter; no auto-promotion UI |
| Platform multi-college SaaS UI | ⚠️ Partially implemented | Schema supports institutions; no separate platform admin app |
| JWT / Node / React stack | ❌ Not implemented | PHP sessions only |

**Legend:** ✅ Implemented · ⚠️ Partially implemented · ❌ Not implemented · 🔍 Needs verification (run seed / manual test)

---

# Professor Module — Today's Enhancements

Documentation for Professor-module features implemented and exercised on **2026-08-25**. Existing core workflows remain; the items below are additive extensions.

**Scope:** Professor module only.  
**No changes were made to other modules as part of this documentation update.**

---

## Professor Dashboard

### Existing (unchanged)

- Active Plans
- Draft Plans
- Submitted Plans
- Recent Course Plans
- Quick Actions

### New

| Feature | Description |
|---------|-------------|
| **Today at a Glance** | Today's Classes, Pending Grading, Low Attendance |
| **Weekly AI Digest** | Summarized weekly academic/AI activity for the signed-in professor |
| **OBE Compliance** | CLO Attainment and PLO Attainment indicators |
| **Cross-Module Risk Detection** | Risk signals spanning Attendance, Internal Marks, and Assignments |
| **Customizable Dashboard Widgets** | Drag-and-drop layout with Save Layout persistence |
| **HOD Benchmarking** | Professor metrics compared with department average |

Tenant and role isolation remain enforced: each professor only sees data for their institution and authorized courses/classes.

---

## New Course Plan

**Route:** `/professor/generate-plan` (and related plan views)

### Existing (unchanged)

- Manual syllabus text entry and AI course-plan generation from typed syllabus text
- Draft → Submitted → Approved / Returned (rejected) HOD workflow
- HOD comments / feedback on plans

### New

- Syllabus **PDF** upload
- Syllabus **DOCX** upload
- Automatic text extraction from uploaded files
- Extracted syllabus text is editable before generation
- AI course-plan generation from uploaded syllabus content
- University / accreditation template support (e.g. **NAAC**, **NBA**, **AICTE**)
- Bloom's taxonomy balance checking
- Versioning when a syllabus/plan is regenerated
- Version comparison
- Co-faculty comments
- NAAC/NBA-oriented export

---

## My Plans

**Route:** `/professor/plans`, `/professor/plan-view`

### Existing (unchanged)

- List and open the professor's own course plans
- Submit drafts for HOD review and view approval status

### New

- Status filtering: Draft, Submitted, Approved, Rejected / returned
- Subject filtering
- Semester filtering
- Version comparison
- Shareable read-only links
- Bulk export of approved plans for accreditation documentation

---

## Lesson Planner

**Route:** `/professor/lessons`

### Existing (unchanged)

- Select Plan → Generate Lesson Plans flow

### New

- Calendar scheduling
- `.ics` calendar export
- AI teaching methodology suggestions based on Bloom level
- Generate Question Bank items from a lesson/session
- Generate PPT from a lesson/session
- Planned vs Actual tracking
- Completed status
- Delayed status
- Suggested learning resources

---

## Question Bank Generator

**Route:** `/professor/questions` (and related paper tools)

### Existing (unchanged)

- Question generation and saving to the professor's personal question bank

### New

- Duplicate / similar-question detection
- Duplicate warning before saving
- Bloom tagging
- CLO tagging
- Unit tagging
- Marks tagging
- Balanced question-paper generation
- Answer key generation
- Marking scheme generation
- Randomized Set A / Set B / Set C
- Post-exam item analysis when real student attempts/results are available

---

## PPT Generator

**Route:** `/professor/ppt`, `/professor/ppt-view`, `/professor/ppt-download`

### Existing (unchanged)

- AI PPT generation from course/plan context

### New

- Institution branding (logo/colors where configured on the institution)
- Speaker notes
- PPTX export
- PDF / handout export where supported
- Shareable presentation option where supported
- Slide-level regeneration
- AI narration **where configured** (disabled until a narration provider is set)
- Student handout generation

---

## Assignments

**Route:** `/professor/assignments`

### Existing (unchanged)

- Assignment creation, publishing, submission viewing, and rubric usage

### New

- AI-generated rubric
- CLO mapping
- Bloom-level mapping
- Student submission portal (existing student assignments surface)
- AI first-pass grading with **professor override**
- Plagiarism / similarity detection where configured
- AI-content detection where configured (off until a provider is set)
- Class performance analytics
- Commonly missed rubric criteria
- Reusable assignment templates
- Bulk assignment creation across sections
- Deadline reminders
- Extension request workflow (student request → professor approve/reject)
- Marks hand-off to Internal Marks

---

## Attendance

**Route:** `/professor/attendance`

### Existing (unchanged)

- Manual attendance marking by class + subject
- Save attendance sessions and percentages used elsewhere (e.g. Internal Marks)

### New

- QR attendance and QR session creation
- QR-based student check-in with expiry / TTL
- Automated attendance shortage alerts
- Predictive attendance risk
- CSV / Excel import and export (with import validation)
- Student attendance regularization requests
- Professor approve / reject regularization workflow
- Attendance heatmap
- Semester / class / subject attendance trends

---

## Internal Marks

**Route:** `/professor/marks`

### Existing workflow (unchanged)

```
Professor → Select Class → Select Subject → Load Students
  → Enter CIA (and related components) → Calculate → Save → Student View
```

- College Admin Formula Configurator remains the source of formula definitions
- Formula priority: subject-specific override → department + subject type → department default → system default
- Class / section / subject isolation

### New

- Automatic formula resolution for the selected class/subject (professor does not pick a formula manually)
- Subject-specific and department + subject-type formula support via Admin configurator
- Automatic **Attendance** mark pull-through from the Attendance module
- Automatic **Assignment** mark pull-through from finalized assignment grades (missing data shown as unavailable — not silently forced to zero unless already required by save validation)
- CIA1 / CIA2 remain professor-entered
- Automatic final internal calculation using the resolved Admin formula
- **What-If simulator** (preview only; does not write to the database)
- Anomaly detection: out-of-range validation (blocking) and sudden CIA drop warning (non-blocking)
- Distribution / moderation view with section-level aggregate statistics
- Formatted mark statement / PDF export

---

## Notifications

**Route:** `/professor/notifications`

### Existing feed (unchanged)

- Filters: **All**, **Approvals**, **System**, **AI**
- Mark all as read
- Per-item read / unread

### New

| Feature | Description |
|---------|-------------|
| **Priority** | High, Medium, Low / informational indicators |
| **Optional priority filter** | High / Medium / Low without removing existing type filters |
| **Actionable notifications** | Safe actions such as Grade Now, View Plan, Update Marks, View Attendance, Approve Now (where role-appropriate) |
| **Daily / Weekly digest** | Summaries built from the signed-in user's own notifications |
| **Delivery channels** | Architecture for In-App, Email, WhatsApp, SMS |
| **Granular preferences** | Per-category channel toggles and digest mode in Professor → Settings |
| **Delivery status** | Per-notification channel status (e.g. Delivered, Not configured, Disabled, Failed) |

**WhatsApp / SMS:** Documented as **configurable**. When provider credentials are not set in server config, the UI reports **Not configured** and does **not** pretend messages were sent. API keys must never appear in the frontend.

Action links are resolved on the server for the authenticated professor (institution + record authorization). Client-supplied record IDs are not trusted.

---

## Security / isolation (Professor enhancements)

Today's Professor-module work preserves:

- Institution / tenant isolation (`institution_id`)
- Department isolation where applicable
- Professor authorization via assigned class/subject only
- Student / class / subject access restrictions
- HOD ↔ Professor role boundaries on plan approval actions
- Secure actionable notification access
- Secure course-plan, marks, attendance, and assignment access

Do not place API keys or provider secrets in client-visible pages; configure providers via server environment / `config` only.

---

## Testing summary (Professor module)

Manual / smoke coverage for today's Professor work included:

- Existing dashboard behavior and new dashboard widgets
- Course Plan generation / syllabus upload path
- My Plans (filters, versions, share/export where used)
- Lesson Planner extensions
- Question Bank extensions
- PPT Generator extensions
- Assignments (including templates, extensions, push to marks where used)
- Attendance (manual + QR / tools where used)
- Internal Marks (formula, pull-through, What-If, anomalies, distribution, statement)
- Notifications (filters, priority, actions, digest, channel status)
- Student visibility of saved marks / assignments / attendance where applicable
- Professor-to-professor isolation and institution / tenant isolation checks

---

**Scope:** Professor module only.

**No changes were made to other modules as part of this documentation update.**

---

## Key files for developers

| Concern | File |
|---------|------|
| Routes | `routes/web.php` |
| Auth | `includes/Auth.php` |
| DB access | `includes/Database.php` |
| Business rules | `includes/helpers.php` |
| HOD courses | `hod/subjects.php` |
| HOD students API | `app/Controllers/Hod/StudentsController.php` |
| Admin users | `app/Controllers/Admin/UserController.php` |
| Legacy routing | `app/Controllers/LegacyController.php` |
| Navigation | `app/Services/NavService.php` |
| Schema | `database/schema.sql` |
| E2E seed | `database/seed_e2e_test_data.php` |
| Data reset | `database/reset_dev_data.php` |

---

## License / context

Built for Indian colleges (OBE, NAAC/NBA, CIA internal marks, Bloom's taxonomy). Extend via `feature_flags`, JSON `meta` fields, and institution `settings` without breaking core tenancy.



<!-- 
DBMS — Course Context

Course Name: Database Management Systems
Course Code: CS301
Department: CSE
Level: UG
Credits: 4

UNIT I — INTRODUCTION TO DATABASE SYSTEMS
Database concepts and characteristics
File system vs. database system
Advantages of DBMS
Database users and administrators
Database system architecture
Data models
Schema and instance
Data independence
Three-schema architecture
Database languages: DDL, DML, DCL
Entity-Relationship (ER) model
Entities, attributes and relationships
ER diagrams and constraints
UNIT II — RELATIONAL MODEL AND SQL
Relational model concepts
Relations, tuples and attributes
Keys: super key, candidate key, primary key and foreign key
Integrity constraints
Relational algebra
Selection and projection
Union, intersection and difference
Cartesian product
Joins
SQL fundamentals
CREATE, ALTER and DROP
INSERT, UPDATE and DELETE
SELECT queries
WHERE, ORDER BY and GROUP BY
Aggregate functions
HAVING clause
Nested queries
SQL joins
Views
UNIT III — DATABASE DESIGN AND NORMALIZATION
Functional dependencies
Trivial and non-trivial functional dependencies
Attribute closure
Functional dependency inference
Database anomalies
Insertion anomaly
Update anomaly
Deletion anomaly
Normalization
First Normal Form (1NF)
Second Normal Form (2NF)
Third Normal Form (3NF)
Boyce-Codd Normal Form (BCNF)
Lossless decomposition
Dependency preservation
UNIT IV — TRANSACTION MANAGEMENT
Transaction concepts
Transaction states
ACID properties
Concurrent transactions
Serial and non-serial schedules
Serializability
Conflict serializability
View serializability
Concurrency control
Lock-based protocols
Two-phase locking (2PL)
Deadlocks
Deadlock prevention and detection
Timestamp-based protocols
Recovery concepts
Log-based recovery
Checkpoints
UNIT V — STORAGE, INDEXING AND ADVANCED DATABASE CONCEPTS
File organization
Storage structures
Indexing concepts
Primary and secondary indexes
Dense and sparse indexes
Multilevel indexing
B-Tree
B+ Tree
Hashing
Static and dynamic hashing
Query processing
Query optimization
Distributed databases
Database security
Authorization and access control
Backup and recovery
Introduction to NoSQL databases -->