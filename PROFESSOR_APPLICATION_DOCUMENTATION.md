# ProProfessor — Complete Application Documentation

This is a developer handover document for the existing **ProProfessor AI** PHP + MySQL application. It describes what the codebase **actually does**, not a product wishlist.

A new developer should be able to read this file alone and understand the product, roles, data flows, modules, database, security, and how to run the project locally.

For the **college-as-customer journey** (purchase → Admin → HOD → Professor → Student → semester/year change), see **[REAL-WORLD COLLEGE CUSTOMER WORKFLOW](#real-world-college-customer-workflow)** at the end of this file.

---

## What ProProfessor is

**ProProfessor AI** is an India-focused academic operating system for a single college (institution). The committed product name in configuration is `ProProfessor AI`, with the tagline *India's AI-Native Academic Operating System*.

It helps a college:

- Set up institution structure (departments, classes/sections, users)
- Let a Head of Department (HOD) own the course catalog and assign professors
- Let professors plan teaching, generate AI-assisted materials, take attendance, enter internal marks, publish assignments, and message students
- Let students see **current** courses, attendance, marks, assignments, PPTs, and a course-aware Ask AI assistant
- Preserve previous-semester activity in **Academic History** when a student’s year or semester changes
- Give College Admin institution-level finance, marks formulas, analytics, NAAC snapshots, and feature flags

The application is **PHP + MySQL**. It is **not** Node.js, React, Laravel, or MongoDB. There is no `package.json`. It is designed to run under **Apache + MySQL (XAMPP or equivalent)**.

---

## What problem it solves

Indian colleges typically split academic work across roles:

| Problem | How this application addresses it |
|---------|-----------------------------------|
| Institution setup is scattered | College Admin owns institution profile, departments, classes, and user accounts |
| Course ownership is unclear | HOD creates subjects/labs and assigns a professor to a specific class/section |
| Students see the wrong semester | Current student views are filtered by department + year + Odd/Even semester + class |
| History must not disappear | Attendance, marks, and assignment records stay in the database; Academic History reads past contexts |
| Internal marks formulas differ by college | Admin (and HOD via the same formula routes) configures scoped formulas; professors do not pick a formula |
| Teaching materials take time | Gemini AI (or offline demo generators) produce course plans, lessons, questions, PPTs, and assignments |
| Accreditation evidence is scattered | Admin NAAC snapshot and HOD department NAAC PDF pull live course-plan statistics |

---

## Who uses it

| Role in `users.role` | Who they are | Scope |
|----------------------|--------------|--------|
| `admin` | College Admin | One institution (`users.institution_id`) |
| `superadmin` | Same admin UI with all permissions | Same institution; **no separate platform-admin app** |
| `hod` | Head of one department | `users.department_id` |
| `professor` | Faculty | Only HOD-assigned `subject_assignments` (course + class) |
| `student` | Learner | Own `class_id` + current academic year/semester |

There is **no Platform Admin UI** in this repository. Multi-institution columns exist (`institution_id` on most tables), but this codebase is a **single-college admin model**.

---

## How the complete system works (high level)

```
College Admin  (institution, departments, classes, users, formulas, finance, NAAC)
        ↓
Department
        ↓
HOD  (course catalog, professor assignment, plan approval, faculty circulars)
        ↓
Professor  (plans, lessons, questions, PPT, attendance, marks, assignments, messages)
        ↓
Student  (current courses, attendance, marks, assignments, PPT, Ask AI, history)
```

**Request flow:**

```
Browser
  → .htaccess (mod_rewrite)
  → index.php (front controller)
  → app/bootstrap.php + routes/web.php
  → MVC controller  OR  LegacyController → professor/ | student/ | hod/*.php
  → MySQL via includes/Database.php (PDO)
  → HTML (app/Views or includes/layout.php)
```

**Hybrid architecture:** Admin pages and role dashboards are MVC. Most academic modules remain legacy PHP scripts, whitelisted in `app/Controllers/LegacyController.php`.

---

# Role hierarchy

| Role | Main responsibility |
|------|---------------------|
| College Admin | Institution and administrative management |
| Superadmin | Same screens as College Admin; all permission checks pass |
| HOD | Department catalog, professor assignment, course-plan review |
| Professor | Teaching tools and student academic management for assigned courses |
| Student | Learning portal and personal academic records |

### Superadmin (separate note)

- Stored as `users.role = 'superadmin'`
- Uses `/admin/*` routes
- `Permissions::can()` returns true for every admin permission
- There is **no** extra platform-wide college switcher or SaaS console

### Sub-admin permissions (College Admin only)

A College Admin can create another `admin` user with a **subset** of modules. Permissions are stored in `users.extra` JSON as `{ "permissions": ["manage_users", ...] }`.

If `extra.permissions` is missing, the admin has **full** access.

| Permission code | Module |
|-----------------|--------|
| `manage_institution` | Institution setup |
| `manage_users` | Users & roles |
| `manage_features` | Feature flags |
| `manage_formulas` | Marks formulas |
| `manage_finance` | Finance |
| `manage_naac` | NAAC builder |
| `view_analytics` | Analytics |
| `manage_billing` | Subscription display |

---

# 1. College Admin

**Auth:** `admin` or `superadmin`. Most pages also call `require_admin_perm(...)`.  
**Tenancy:** Queries use the logged-in user’s `institution_id`.  
**Legacy wrappers:** `admin/*.php` only redirect to MVC routes.

---

## Admin Dashboard

| Item | Detail |
|------|--------|
| Route | `GET /admin/dashboard` |
| Files | `app/Controllers/Admin/DashboardController.php`, `app/Views/admin/dashboard.php` |

**Cards that actually exist:**

| Card | Source | Notes |
|------|--------|-------|
| Users | `COUNT` of all `users` in the institution | Label says “Active accounts” but **inactive users are included** |
| Course plans | `COUNT` of `course_plans` | All statuses |
| Students | `COUNT` of `users` with `role = student` | **Includes inactive students** |
| Expenses | `SUM(amount)` from `expenses` | **All-time** total; displayed with a `$` symbol (Finance uses `₹`) |

**Not on this dashboard** (those live on Analytics or other pages): department count, HOD count, professor count, AI usage, department expense breakdown.

**Quick actions:** Users, Feature flags, Finance, Marks formulas, NAAC builder, Subscription.

**CRUD:** None. Read-only overview.

---

## Institution Setup

| Item | Detail |
|------|--------|
| Route | `GET/POST /admin/institution` |
| Permission | `manage_institution` |
| Files | `InstitutionController`, `app/Views/admin/institution.php` |

### Institution information Admin can edit

Saved to `institutions`:

| Column / setting | Meaning |
|------------------|---------|
| `name` | College name |
| `affiliation_university` | University affiliation |
| `naac_grade` | NAAC grade |
| `academic_year` | e.g. `2025-26` — used when HOD assigns professors |
| `current_semester` | Fallback semester for students who have no personal semester |
| `city`, `state` | Location |
| `logo_url` | Logo URL (used in PPT branding and some PDFs) |
| `settings.attendance_min` | Shortage threshold (default 75) |
| `settings.brand_primary/secondary/accent` | Hex colours for branding |
| `settings.geofence_lat/lng/radius_m` | QR attendance geofence |
| `settings.geofence_required_for_qr` | Whether GPS is required for QR check-in |

**Present in the database but not on this form:** `address`, `pincode`, `phone`, `email`, `code`, `nba_status`, `subscription_tier`, `licensed_seats`. Some of those still appear on NAAC PDFs if already stored.

### Department management

| Action | Implemented? |
|--------|----------------|
| Create | Yes — name + code |
| Edit | **No** |
| Delete / deactivate | **No** |

Departments belong to the institution (`departments.institution_id`). `hod_user_id` is set when Admin creates/updates an HOD user, not from this page.

### Classes (year / section / UG-PG)

Admin creates **classes**, not courses.

A class row is the academic group:

| Field | Typical value |
|-------|----------------|
| `department_id` | CSE |
| `year` | 1–4 |
| `section` | A, B, C |
| `name` | Display label |
| `meta.level` | `UG` or `PG` |
| `academic_year` | Copied from institution when created via InstitutionController |

**Where the UI lives:** the **Users** page has the add-class form.  
`InstitutionController` also implements `action=add_class` (including an optional semester field), but **`institution.php` does not render a class form**. The controller passes `$classes` to the view; the view does not use it.

**HOD cannot create classes.**

### Academic courses catalog (read-only)

The Institution page lists HOD-created `subjects` with filters (department, year, semester, theory/lab). Admin **cannot** create, edit, or delete subjects here.

### Who is affected

- Institution `academic_year` / `current_semester` affect HOD assignments and student semester fallback
- Branding and geofence affect PPT and QR attendance
- Departments constrain HOD / professor / student accounts

---

## Users & Roles (HOD, Professor, Student, Admin)

| Item | Detail |
|------|--------|
| Route | `GET/POST /admin/users` |
| Permission | `manage_users` |
| Feature flag | `user_management` (nav) |
| File | `app/Controllers/Admin/UserController.php` |

### What Admin can do

| Action | Details |
|--------|---------|
| Create | Professor, student, HOD, admin |
| Edit | Name, email, department, class, year, semester, phone, employee/register ids, sub-admin permissions |
| Deactivate / reactivate | `is_active` toggle |
| Reset password | Minimum 8 characters |
| Import | CSV / TXT / TSV (not real Excel) |
| Download | CSV template (`?export=template`) |
| Create class | Inline on this page |
| Hard delete user | **No** |

**Protected account:** user id `1` or email `admin@proprofessor.local` cannot have role changed, be deactivated, or have password reset from this page.

### Fields by role

| Field | HOD | Professor | Student | Admin |
|-------|-----|-----------|---------|-------|
| Full name, email, password | Required | Required | Required | Required |
| Department | Required | Required | Required | Optional |
| Class (`class_id`) | — | — | **Required** | — |
| Academic year level 1–4 | — | — | **Required** | — |
| Semester Odd/Even | — | — | **Required** | — |
| Register number | — | — | Optional | — |
| Employee id | Optional | Optional | — | Optional |
| Sub-admin permissions | — | — | — | Optional |

**Business rules:**

- Student class year must match `academic_year_level`
- Student class must belong to the student’s department
- Creating/updating an HOD sets `departments.hod_user_id`
- Duplicate email is rejected (unique `users.email`)
- Default password on create is defined in the installer / user-create path (do not hardcode production passwords)

### CSV import columns

`full_name, email, role, department_code, class_name, employee_id, register_no, password, phone`

### Current academic context (students)

```
Student user row
  → institution_id
  → department_id
  → class_id  (section + UG/PG via classes.meta)
  → academic_year_level  (1–4)
  → semester             (Odd Semester / Even Semester)
        ↓
HOD subject catalog matching year + semester + department
        ↓
subject_assignments for that class
        ↓
Professor name (or empty → UI shows “Not Assigned”)
```

Changing year/semester/class **does not delete** attendance, marks, or submissions. Current portal views switch immediately after `Auth::refresh()` on the next page load.

---

## Marks Formula

| Item | Detail |
|------|--------|
| Route | `GET/POST /admin/formulas` |
| Permission | Admin needs `manage_formulas` |
| Extra | **HOD can also open these same routes** (`FormulaController` allows `hod`). There is **no HOD sidebar item** for formulas. |
| Files | `FormulaController`, `app/Models/MarksFormula.php` |

### What Admin (or HOD) can do

| Action | Implemented? |
|--------|----------------|
| Create formula | Yes |
| Edit formula | Yes (`?edit={id}`) |
| Delete formula | **No** |
| AI parse plain English | Yes — `POST /api/ai?module=formula` |

### Scope (actual priority when a professor opens Internal Marks)

1. **Subject override** (`subject_id` set)
2. **Department + subject type** (`theory` / `lab`)
3. **Department default** (department set, type empty)
4. **Institution default** (`is_default = 1` or no department)
5. **System fallback** if nothing is configured:

```
Name: CBCS fallback · CIA average to 25
Expression: ((cia1+cia2)/2)*(25/50)
Components: CIA 1 /50, CIA 2 /50
```

There is **no separate “activate” flag**. The latest matching row wins at each priority level.

### Seed / typical college formula

`database/seed.sql` inserts a Madurai-pattern default:

- Plain English: average of CIA1 and CIA2 scaled to 15, plus Assignment 5 and Attendance 5, total 25
- Expression: `((cia1+cia2)/2)*(15/50) + assignment + attendance`
- Total max: 25

### Storage

Table `marks_formulas`: `name`, `pattern`, `plain_english`, `components` (JSON), `expression`, `total_max`, `department_id`, `subject_type`, `subject_id`, `is_default`, `ai_parsed`.

Expressions are **not** run with `eval()`. `MarksFormula` substitutes component codes and uses a recursive-descent arithmetic parser (`+ - * / ( )` only).

### Who is affected

Professors (automatic formula on Internal Marks) and students (computed totals / grade letters).

---

## Finance

| Item | Detail |
|------|--------|
| Route | `GET/POST /admin/finance`, `GET /admin/finance/pdf` |
| Permission | `manage_finance` |
| Feature flag | `finance` |
| Files | `FinanceController`, `app/Models/Expense.php`, `app/Services/FinanceExpensePdf.php` |

### What Admin can do

| Action | Implemented? |
|--------|----------------|
| Add expense | Yes |
| Edit / delete expense | **No** |
| Download monthly PDF | Yes (`scope=month`) |
| Download yearly PDF | Yes (`scope=year`) |

### Create fields → `expenses`

| Field | Notes |
|-------|-------|
| Category | Hardcoded options: Salaries, Lab & Library, Infrastructure, Events, Utilities, Other |
| Department | Optional; empty = institution-wide |
| Title | Required |
| Amount | Decimal |
| Date | `expense_date` |
| Vendor | Optional |
| Payment mode | Optional |
| Added by | Current admin user |

`expense_categories` is seeded but the UI does **not** manage that table. `budgets` exists in schema and is **unused** by the Finance UI.

### Totals shown

| Metric | Behaviour |
|--------|-----------|
| Yearly total | Current **calendar year** |
| Monthly total | Selected month (`?month=`) |
| Highest category | Current calendar year |
| Ledger | Paginated (4 per page) for that year/month |
| Year archives | One panel per year that has rows (plus current year) with 12-month breakdown |

Records are **not deleted on 1 January**. Year filtering is `YEAR(expense_date)`. Historical years remain.

### Who is affected

Admin only for writes. Analytics reads the same `expenses` table.

---

## NAAC Builder

| Item | Detail |
|------|--------|
| Route | `GET /admin/naac`, `GET /admin/naac?download=pdf` |
| Permission | `manage_naac` |
| Feature flag | `naac_reports` |

**This is not a full SSR/AQAR form builder.** It is a **live snapshot** of course-plan activity.

### What it collects / shows

1. **Course plan compliance** — counts from `course_plans` by `status` (`draft`, `submitted`, `under_review`, `approved`, `returned`)
2. **Faculty matrix** — active professors, plan count, `AVG(ai_score)`
3. **Snapshot summary** — faculty count, faculty with plans, approved plans, average AI score, report date

### PDF

Generated on the fly with `includes/SimplePdf.php`. Letterhead uses institution `name`, address fields, `naac_grade`, affiliation, academic year, semester, `nba_status`.

Nothing is stored as a NAAC document row. There is no criterion-wise questionnaire.

---

## Admin Analytics

| Item | Detail |
|------|--------|
| Route | `GET /admin/analytics` |
| Permission | `view_analytics` |
| File | `AnalyticsController` |

**Implemented metrics:**

| Section | Source |
|---------|--------|
| AI generations (total) | `COUNT(*)` from `ai_generations` |
| Average plan score | `AVG(ai_score)` from `course_plans` |
| Top AI department | `ai_generations` joined to users/departments |
| Active people | Active students + professors + HODs |
| AI usage by department | Horizontal bars |
| People by role | Chart.js doughnut |
| Readiness index | Heuristic: 35% if any AI call, 35% if avg score exists, 30% if people > 0 |
| People by department | Professor / student / HOD counts |
| Expenses by department | Current calendar year + current month; unassigned institution totals |

No date-range picker. No department-wise student/professor charts beyond the people-by-department cards.

---

## Subscription (Billing)

| Item | Detail |
|------|--------|
| Route | `GET /admin/billing` |
| Permission | `manage_billing` |

**Display only:**

- `institutions.subscription_tier`
- `institutions.licensed_seats`
- Seats used = count of `professor` + `hod` + `admin`

A static price comparison table is marketing copy. **No payment gateway, no upgrade flow, no seat enforcement.**

---

## Admin Notifications (Admin → HOD)

| Item | Detail |
|------|--------|
| Route | `GET/POST /admin/notifications` |
| Files | `NotificationController`, `includes/AdminHodMessageTools.php` |

### What Admin can do

- Send a message to **all active HODs** in the institution (not an individual-HOD picker)
- Optional title (max 200), body (max 4000)
- Optional **PDF or DOCX** attachment (max 10 MB, from `upload_max_mb`)
- View last 15 sent messages
- Delete a sent announcement (also deletes HOD notifications and the file)

### Storage

1. Runtime table `admin_hod_announcements`
2. One `notifications` row per HOD (`type = announcement`, `meta.kind = admin_hod_message`)
3. Attachment download: `/api/messages/attachment?source=admin_hod&id=`

### UI limitation

When the current user can message HODs, `app/Views/shared/notifications.php` shows **only** the HOD messaging panels. The admin’s **personal inbox is hidden** on this page.

Email / WhatsApp / SMS channels exist in `NotificationService` but stay **Not configured** unless server credentials are set. Keys must never be placed in frontend code.

---

## Feature Flags

| Item | Detail |
|------|--------|
| Route | `GET/POST /admin/features` |
| Permission | `manage_features` |

Admin can **toggle** catalog flags per institution (`institution_features`). Admin cannot create new flag codes in the UI.

Seeded codes (`database/seed.sql`):

| Code | Default | Used for |
|------|---------|----------|
| `ai_course_plan` | On | Professor New Course Plan nav |
| `bloom_mapper` | On | Seeded; Bloom runs via AI modules |
| `ai_review` | On | Seeded |
| `improve_ai` | On | Seeded |
| `lesson_planner` | On | Lesson Planner nav |
| `question_bank` | On | Question Bank nav |
| `ppt_generator` | On | PPT nav |
| `assignment_ai` | On | Assignments nav |
| `attendance` | On | Attendance nav |
| `internal_marks` | On | Internal Marks nav |
| `version_control` | On | My Plans nav |
| `notifications` | On | Professor notifications nav |
| `student_portal` | On | Student My Courses nav |
| `ask_ai` | On | Ask AI nav |
| `hod_approvals` | On | HOD Approvals nav |
| `dept_analytics` | On | HOD Analytics nav |
| `naac_reports` | On | Admin NAAC + HOD Reports nav |
| `finance` | On | Admin Finance nav |
| `user_management` | On | Admin Users nav |
| `api_hub` | **Off** | Marked coming soon; **no integration hub UI** |

**Important:** flags hide **sidebar items**. Legacy HOD/professor/student URLs generally still work if typed directly.

---

# 2. HOD

**Auth:** `hod` (Faculty page is HOD-only). Most other HOD pages also allow `admin`.  
**Isolation:** `hod_department_id()` returns the session department for HOD; `0` for admin.  
**Admin override:** several pages accept `?department_id=` for admin. Dashboard, Timeline, and Faculty do **not**.

HOD does **not** create professor or student accounts. HOD does **not** create classes.

---

## HOD Dashboard

| Route | `GET /hod/dashboard` |
| Files | `Hod/DashboardController`, `app/Views/hod/dashboard.php` |

**Shown:**

| Stat | Source |
|------|--------|
| Pending approvals | `course_plans` status `submitted` or `under_review` |
| Approved plans | Status `approved` |
| Faculty count | Active professors in the department |
| Average AI score | `AVG(ai_score)` |
| Open compliance alerts | Up to 5 rows from `compliance_alerts` |

**Limitation:** nothing in the application **inserts** `compliance_alerts` during normal use (only seed/reset and a delete cascade on plan delete). The panel is **read-only / often empty**.

---

## Faculty

| Route | `GET/POST /hod/faculty` |
| Auth | **HOD only** (admin gets 403) |

**See:** paginated professors with plan totals, approved count, pending count, average AI score.

**Create:** department circular → `announcements` (`circular`, `deadline`, `exam`, `general`). Notifies all active professors in the department.

**Cannot:** edit professor accounts, assign courses (that is Courses), or delete announcements.

---

## Students

| Route | `GET /hod/students`, `GET /api/hod/students` |

**Read-only roster** of active department students.

**Filters:** year (1–4), section, class, program UG/PG (`classes.meta.level`), search (name/email/register), pagination (10/page).

**JSON API:** `Hod/StudentsController` — same department isolation.

---

## Course Management (`/hod/subjects`)

This is the **only place courses are created**.

### Create / update course or lab

`hod_save_subject()` writes `subjects`:

| Field | Storage |
|-------|---------|
| Code | `subjects.code` (unique per institution) |
| Name | `subjects.name` |
| Credits | Default 4 theory / 2 lab in the UI |
| Contact hours | Default 60 / 30 in the UI |
| Syllabus | `syllabus_text` |
| Semester | `Odd Semester` / `Even Semester` |
| Year 1–4 | `subjects.meta.year` |
| Type | `subjects.meta.course_type` = `theory` or `lab` |

Saving the same code in the same department **updates** the row. A code owned by another department is rejected.

**No subject delete.**

**Filters:** year chips, odd/even semester, Courses vs Labs.

### Assign professor + class

`hod_assign_professor_subject()`:

1. Subject, professor, and class must all be in the HOD’s department and institution
2. Class year must match subject year
3. Upserts `subject_assignments` (`subject_id`, `professor_id`, `class_id`, institution `academic_year`, subject semester)
4. Calls `enroll_class_students_in_subject()` — only **current** students whose year/semester/class match the course context get `enrollments` rows

**Remove assignment:** deletes the `subject_assignments` row only. Historical student records are not deleted.

---

## Course Plan Review (`/hod/approvals`)

Queue: plans with status `submitted`, `under_review`, `returned`, `approved` (not `draft`).

| UI action | `course_plans.status` | `plan_reviews.action` |
|-----------|----------------------|------------------------|
| Save comments | `under_review` | `comment` |
| Approve | `approved` | `approve` |
| Request changes | `returned` | `request_changes` |
| Return | `returned` | `reject` |

Point-by-point feedback is JSON in `hod_comments` via `HodFeedback` (`ok` / `suggest` / `must_fix` on overview, outcomes, units, Bloom, weekly, resources, advice).

Professor is notified and sent to `/professor/plan-view.php?id=`.

Drafts cannot be approved. The professor must submit first.

---

## HOD Analytics

| Route | `GET /hod/analytics` |

**Live metrics:** students by year, professor count, plan count, average AI score, professor theory/lab workload, Bloom K1–K6 averages, submission status bars, comparative subject scores.

Read-only. No year/section filter on this page (admin may pass `department_id`).

---

## Complaints (`/hod/compliance`)

**Nav label is “Complaints”.** This is **not** NAAC regulatory compliance.

It is a **professor → HOD message inbox** (`professor_hod_messages`) with threaded HOD replies and optional PDF/DOCX attachments.

---

## Timeline

| Route | `GET /hod/timeline` |

Read-only:

- Institution-wide `academic_events`
- Department `course_plans` submitted/reviewed dates

HOD cannot add milestones. Admin without `department_id` gets a weak/empty plan list.

---

## NAAC Reports (`/hod/reports`)

Department evidence pack:

- On-screen table: subject, status, AI score, Bloom K4–K6 %, version, updated date
- PDF download with institution letterhead, department snapshot, status distribution, criterion-style register

Bloom K4–K6 % = share of K4+K5+K6 from `course_plans.bloom_data`.

---

## HOD Notifications

MVC inbox at `/hod/notifications`: filter, mark read, delete, digest, safe action links.

HOD cannot send Admin→HOD broadcasts.

---

## Academic configuration HOD does **not** own

| Config | Owner |
|--------|--------|
| Classes / sections | College Admin |
| Institution academic year / semester | College Admin |
| Student year/semester | College Admin |
| Marks formulas (UI) | Admin formulas page (HOD may open the URL) |
| Calendar events | Seed / other writers; lesson planner can add `lesson_session` events |

---

# 3. Professor

**Auth:** `professor` (some legacy pages also allow `admin`).  
**Authorization helpers:** `professor_can_manage_class()`, `professor_can_manage_subject()` — based on `subject_assignments`, not UI hiding.

```
Professor
  → subject_assignments
  → Course (subjects) + Class (year / section)
  → Department / Institution
```

Professors **cannot create subjects**. `save_professor_subject()` throws.

---

## Professor Dashboard

| Route | `GET /professor/dashboard`, `POST /professor/dashboard/layout` |

**Real widgets** (from `ProfessorDashboardInsights` and `CoursePlan` counts):

| Widget | Data |
|--------|------|
| Active / draft / submitted plans | `course_plans` |
| Recent plans | Last 5 |
| Quick actions | Links |
| Today at a glance | Today’s sessions, pending grading, low attendance |
| Weekly AI digest | Week counts; Gemini may paraphrase if configured |
| OBE compliance chips | CLO / Bloom presence on plans — **not** official attainment |
| At-risk students | Attendance + marks + assignment signals |
| Department benchmark | Professor vs department aggregates |
| Widget layout | Saved in `users.preferences.dashboard_widgets` |

---

## Assigned Courses

`professor_subjects()` / `professor_manageable_classes()` read `subject_assignments`.

A professor only manages the **class + subject pairs** HOD assigned. Attendance, marks, assignments, and student messaging all reuse `students_for_current_course_context()` so the roster matches students whose **current** year/semester/class match the course.

---

## Course Plan

| Routes | `/professor/generate-plan`, `/plans`, `/plan-view`, `/plan-compare`, `/plan-export` |

### Create / generate

1. Select assigned course / class (or enter subject text)
2. Paste syllabus **or** upload PDF/DOCX (`/api/ai?module=syllabus_extract`, max 8 MB). Extracted text is editable. The file is **not stored**.
3. Optional accreditation template: Standard / NAAC / NBA / AICTE (prompt hint only)
4. AI (`module=course_plan`) or demo generator if no Gemini key
5. Saved to `course_plans` + `plan_units` as **draft**

### Workflow

```
draft → (professor submits) submitted → under_review / approved / returned
```

Same-department professors may leave comments in `plan_reviews`. Only the owner submits.

### Versioning, share, export

- Regeneration with `plan_id` snapshots the previous version in `course_plan_versions`
- Compare: `/professor/plan-compare`
- Share: **approved plans only** — public token at `/share/plan.php?t=...` (no login)
- Export: HTML/NAAC-style export; bulk approved-package PDF from My Plans
- Bloom balance warning if K1+K2 ≥ 55% (advisory)

---

## Lesson Planner

| Route | `/professor/lessons` |

- Generate sessions from an owned course plan (`/api/ai?module=lesson` or non-AI split by unit hours)
- Fields: session number, title, duration, objectives, teaching method, activities, formative assessment, engagement, materials
- Status: `planned` / `completed` / `delayed` plus planned/actual dates
- Calendar: upserts `academic_events` (`event_type = lesson_session`) and can download ICS (fixed 09:00 start)
- Deep links into Question Bank and PPT with unit/topic prefilled
- Resource suggestions are **rule-based placeholders** (often no URL)

---

## Question Bank

| Routes | `/professor/questions`, `/professor/question-paper` |

### Generation and bank

- AI `module=questions`: MCQ / short / long / essay, Bloom K-level, unit, count
- Saved to `question_banks` + `questions`
- CLO tags inferred from plan outcomes; editable
- Similarity warning (Jaccard / coverage / `similar_text`, threshold ~62%)

### Answers — important

**The question bank list does not show correct answers.**  
Copy in the UI: answers are hidden in the bank. Answer keys appear on the **question paper** view (`question-paper.php?view=key`). Bank PDF also **excludes** `correct_answer`.

### Question paper builder

- Parts with count × marks and Bloom mix
- Equivalent Set A / B / C when the bank is large enough
- PDF via `QuestionBankTools` with institution letterhead
- Item analysis only if `question_attempts` has **≥ 10** rows per question (often empty on fresh installs)

Runtime tables: `exam_papers`, `question_attempts`.

---

## PPT Generator

| Routes | `/professor/ppt`, `/ppt-view`, `/ppt-download`, `/ppt-pdf`, `/ppt-handout` |

**Working:**

- AI `module=ppt` → `presentations.slides` JSON, status `ready`
- Branding from institution logo + brand colours
- Professor name and course title on slides
- PPTX via `PptxExporter` (speaker notes included)
- PDF deck and condensed handout
- Per-slide regenerate (Gemini or `LectureSlideBuilder` fallback)

**Prototype / not wired:**

- **AI narration** — button exists; no voice provider adapter
- **Google Slides** — config exists (`google_slides.enabled = false`); not implemented

Students see decks with status `ready` or `published` for **current** subjects only.

---

## Assignments

| Route | `/professor/assignments` |

**Working:**

- Create manually or via AI `module=assignment` (published)
- Class + subject must be assigned
- Types include essay, case study, research review, problem solving, mini project, mixed, lab, reflection, group presentation
- Rubric with marks / CLO / Bloom; totals must match `max_marks`
- Templates (`assignment_templates`)
- Bulk create across selected sections
- Student text + optional file submission; late allowed (`status = late`)
- Extension requests: student asks → professor approve/reject
- Similarity report: pairwise text ≥ 35% (in-app, not Turnitin)
- AI first-pass grade stored as **provisional** in submission meta; professor must Finalize
- Push finalized grades into Internal Marks assignment component (`pushToInternalMarks`)
- Deadline reminders via notifications

**Prototype:** AI-content-detection config flag — **no scoring provider** until configured.

---

## Attendance

| Route | `/professor/attendance` |

**Working:**

- Select assigned class + subject
- Roster = current matching students (`students_for_current_course_context`)
- Mark present / absent / late / excused
- Stored in `attendance_sessions` + `attendance_records` (JSON `records` also on the session)
- Present calculation: **present + late**
- QR session tokens (`attendance_qr_tokens`); students open `/student/attendance-qr.php?token=`
- Optional geofence from institution settings
- CSV/Excel roster and attendance import; CSV export; monthly PDF
- Regularization requests (student → professor approve/reject)
- Heatmap / weekly trend
- Shortage alerts vs `institution_attendance_min()` (default 75)

Historical sessions for other years/semesters are **not deleted** when the student is promoted.

---

## Internal Marks

| Route | `/professor/marks` |

```
Select class → select subject → load current students
  → CIA (and other manual components)
  → Attendance auto-scaled from Attendance module
  → Assignment auto-scaled from finalized grades
  → Formula compute → save → student current view
```

**Rules:**

- Formula is resolved server-side; professor does not choose it
- Out-of-range component values are **blocked**
- CIA1→CIA2 drop warning is **non-blocking**
- What-If preview does **not** write the database
- Distribution/moderation stats for the class/subject
- Mark statement PDF download
- Unique key includes `academic_year` (added at runtime if missing)

Unique storage: `internal_marks` (`subject_id`, `class_id`, `register_no`, `academic_year`).

---

## Message Students

| Route | `/professor/messages` |

```
Year → Course → Class/section
  → Recipients = current matching students
  → Title + message
  → Optional PDF/DOCX (max 10 MB)
  → professor_announcements + per-student notifications
  → Student downloads via /api/messages/attachment
```

Professor can delete a sent announcement (file + notifications).

---

## Message HOD

| Route | `/professor/message-hod` |

Threads in `professor_hod_messages` to the active HOD of the professor’s department. Optional PDF/DOCX. HOD replies on `/hod/compliance`.

---

## Settings and notifications

- **Settings:** name, phone, password; notification preferences (in-app, email, WhatsApp, SMS, digest). External channels stay unused until configured.
- **Notifications:** shared inbox with type/priority filters, mark read, delete, safe `?go=` actions, digest.

---

# 4. Student

**Auth:** `student` only on student routes.  
**Context is read-only.** Students cannot change year, semester, section, or UG/PG.

On dashboard load, `Auth::refresh()` reloads the user row so Admin edits apply without a new login.

---

## Student Dashboard

| Route | `GET /student/dashboard` |

| Panel | Content |
|-------|---------|
| Welcome | First name + Ask AI link |
| Stats | Course count, open assignments, announcement count, Ask AI |
| My Academic Details | Year, department, section, semester — “admin manages these” |
| My Subjects / My Labs | Current catalog + professor or **Not Assigned** |
| Open assignments | Up to 5 with due date |
| Quick actions | Course PPT, Assignments, Attendance, Ask AI |

Announcement **count** is shown; a full announcement list is **not** rendered on the dashboard (calendar/notifications cover comms).

---

## Current Academic Context

| Field | Stored on | Fallback |
|-------|-----------|----------|
| Institution | `users.institution_id` | — |
| Department | `users.department_id` | Class department |
| Class / section | `users.class_id` → `classes.section` | — |
| UG / PG | `classes.meta.level` | Shown on Attendance and Calendar |
| Year 1–4 | `users.academic_year_level` | `classes.year` |
| Semester | `users.semester` | `institutions.current_semester`, else Odd |

Columns `academic_year_level` and `semester` are added at runtime by `ensure_student_academic_schema()` if missing.

---

## My Subjects / My Courses

| Route | `/student/courses` |

```
Student year + department + Odd/Even
  → subjects_for_department_context()  (HOD catalog)
  → For each subject + student's class_id
  → subject_assignments → professor name
  → If none: empty name → UI "Not Assigned"
```

If year or department is missing (legacy), the app falls back to active `enrollments` for the student’s class and institution academic year.

Approved course-plan Bloom/AI score may be attached for display.

**Attendance page** uses the longer label **“Professor Not Assigned”** when the name is empty.

---

## Academic Year / Semester Change (actual behaviour)

Students **do not** change this themselves. College Admin edits the user.

**Example — semester flip**

```
Year 1 + Odd Semester
  → Student sees Odd catalog (e.g. DBMS) for Section A
Admin sets semester = Even
  → Auth::refresh()
  → Student sees Even catalog for Year 1
  → Odd attendance/marks/assignments remain in DB
  → Academic History lists Year 1 Odd if those records exist
```

**Example — year promotion**

```
Year 1 → Year 2 (and usually a Year-2 class_id)
  → Current views use Year 2 catalog
  → HOD must have Year 2 subjects and assignments
  → Year 1 records remain historical
```

There is **no automatic bulk promotion** screen. Data is **not deleted**.

Current professor rosters use `student_matches_course_context()` so promoted students **drop off** last semester’s attendance/marks class lists.

---

## Attendance (student)

| Route | `/student/attendance` |

- One card per **current** subject
- Sessions for the student’s `class_id` and those subject ids
- Present = present + late
- Colour vs institution minimum %
- Regularization request for absent/late/excused (current subjects only)

Previous terms: **Academic History**, not this page.

### QR check-in

`/student/attendance-qr.php?token=...`

Validates token, expiry, institution, class. Optional geofence. Marks **present**. Duplicate present check-in is blocked.

---

## Internal Marks (student)

| Route | `/student/marks` |

Subject-wise components, computed total, grade letter for **current** `courses_for_student()` ids and current class. Optional filter by institution academic year.

Past marks: Academic History detail.

---

## Assignments (student)

| Route | `/student/assignments` |

- Published assignments for current class + current subjects
- Submit **text** (required) + optional file under `uploads/assignments/`
- Late submissions allowed
- After grading: grade + feedback; form hidden
- Extension request with reason and requested date
- History UI shows **counts** (submitted/graded/total), not full past file contents

---

## Academic History

| Route | `/student/academic-history` |
| File | `includes/StudentAcademicHistoryTools.php` |

**Read-only.** Does not modify or delete.

Contexts are unique `(class_id, year, semester_key)` built from **real activity**:

1. Attendance records
2. Internal marks
3. Assignment submissions

**Not** from enrollments alone. **Current** context is excluded.

Drill-down: year group → subjects → subject detail (professor, attendance %, assignment stats, latest marks + components, optional question attempts).

Access is checked with `studentOwnsContext()`.

---

## Course PPT

| Route | `/student/notes` plus `ppt-view` / `ppt-download` / `ppt-pdf` |

Lists `presentations` with status `ready` or `published` for current subjects. View/download reuse professor renderers but **block** regenerate/narration/Google Slides POSTs.

Gate: `presentation_accessible()` — same institution, published/ready, subject in current courses (or legacy enrollment).

---

## Ask AI

| Route | `/student/ask-ai` |

```
Select current subject (from courses_for_student)
  → Question
  → Materials: approved course plan + up to 10 published documents
  → Gemini (if keyed) OR offline snippet matcher
  → Stored in ai_chats / ai_chat_messages
```

**Irrelevant-question handling (implemented):**

- System prompt: answer **only** in the selected subject; refuse off-topic; do not invent missing syllabus
- Offline path scores the question against other subjects; if another subject wins, the reply tells the student which subject to select
- Opening a chat **locks** that chat to its `subject_id`
- Student never sees raw “API key missing” errors — offline fallback is used

Professor/admin Ask AI **requires** a configured Gemini key (HTTP 503 otherwise).

---

## Calendar

| Route | `/student/calendar` |

Read-only table of UG/PG, year, department, section, semester.

Events: `academic_events` with `event_type = lesson_session` linked to course plans for the student’s class and **current** subjects.

Students cannot add events or change context.

---

## Notifications and professor files

| Route | `/student/notifications` |

Filters (all / approval / system / AI / announcement), priority, read, delete, digest, safe action URLs.

Professor messages with attachments show a download link: `/api/messages/attachment?id=`.

---

# 5. End-to-End Academic Data Flow

```
College Admin
  → Institution (academic_year, current_semester)
  → Department (e.g. CSE)
  → Class (UG, Year 1, Section A)
  → Users: HOD, Professor Arun Kumar, Student
        ↓
HOD
  → Subject CS301 Database Management Systems
       meta.year = 1, semester = Odd Semester
  → Assign Arun Kumar + Year 1 Section A
  → subject_assignments + enrollments for matching students
        ↓
Student (Year 1, Sec A, Odd)
  → Sees DBMS + “Arun Kumar”
        ↓
Professor
  → Course plan → HOD approve
  → Lessons, questions, PPT
  → Attendance, internal marks, assignments, messages
        ↓
Student current portal
  → Those records
```

### When Admin changes Odd → Even

- Current course list becomes Even-semester HOD catalog
- Arun Kumar no longer appears unless also assigned to an Even course
- Odd DBMS attendance/marks/assignments stay in tables
- Academic History shows Year 1 Odd if the student had activity

### When Admin changes Year 1 → Year 2

- Same preservation
- HOD must create/assign Year 2 courses for the new class
- Year 1 activity remains historical

---

# 6. Current Academic Data vs Academic History

| Surface | Scope | Mechanism |
|---------|--------|-----------|
| My Courses, Ask AI, Calendar PPT list | Current year + semester + department catalog | `courses_for_student()` |
| Current attendance / marks / assignments | Current subject ids + `class_id` | Same helper |
| Professor roster | Students matching course year/semester/class | `student_matches_course_context()` |
| Academic History | Past `(class, year, semester)` with activity | `StudentAcademicHistoryTools` |

**Do not assume history is deleted.** The code is written to **leave old rows** and **stop showing them on current pages**.

Internal marks additionally snapshot `academic_year` so two years of the same class/subject/register do not collide.

---

# 7. Database Architecture

**Database name (default):** `proprofessor`  
**Schema file:** `database/schema.sql`  
**Engine:** InnoDB, `utf8mb4`  
**Documented compatibility:** MySQL 8.0+ / MariaDB 10.5+

Runtime `ALTER TABLE` / `CREATE TABLE IF NOT EXISTS` helpers extend the schema (student year/semester, formula scope columns, QR tables, message tables, etc.). Do not invent table names beyond what schema + those helpers create.

### Core tables

| Table | Purpose | Important columns | Who uses it |
|-------|---------|-------------------|-------------|
| `institutions` | Tenant college | `academic_year`, `current_semester`, `settings`, branding, seats | Admin |
| `departments` | Dept per institution | `hod_user_id`, `code` | Admin, HOD |
| `programs` | UG/PG catalog | `level`, `duration_years` | Seed / E2E seed; **no Admin programs page** |
| `classes` | Year + section group | `year`, `section`, `meta.level` | Admin, all roles |
| `users` | All people | `role`, `department_id`, `class_id`, `academic_year_level`, `semester`, `extra`, `preferences` | All |
| `password_resets` | Token table | `token`, `expires_at` | **No login “forgot password” UI** |
| `subjects` | Course/lab catalog | `code`, `semester`, `meta.year`, `meta.course_type` | HOD, others read |
| `subject_assignments` | Who teaches what | `professor_id`, `class_id`, `academic_year` | HOD write; professor/student read |
| `enrollments` | Student ↔ subject | `status`, `academic_year`, `semester` | Auto on assignment |
| `course_plans` | Teaching plans | `status`, `plan_data`, `bloom_data`, `hod_comments` | Professor, HOD |
| `course_plan_versions` | Snapshots | `snapshot` JSON | Professor |
| `plan_units` | Units | hours, topics, outcomes, Bloom | Professor, HOD |
| `plan_reviews` | Review trail | `action`, `comments` | HOD, co-faculty |
| `lesson_plans` | Sessions | `session_status`, dates | Professor; calendar |
| `question_banks` / `questions` | Bank | Bloom, CLO, options, `correct_answer` | Professor |
| `exam_papers` | Built papers | `sets_data`, `answer_key` | Runtime helper |
| `question_attempts` | Item analysis | Per-question attempts | Runtime helper |
| `presentations` | PPT JSON | `slides`, `status` | Professor, student |
| `assignments` / `assignment_submissions` | Work | deadline, grade, `file_url` | Professor, student |
| `assignment_templates` | Reuse | Runtime | Professor |
| `assignment_extension_requests` | Extensions | Runtime | Both |
| `students_roster` | Register mirror | `register_no` | Attendance/marks |
| `attendance_sessions` / `attendance_records` | Attendance | date, status | Professor, student |
| `attendance_qr_tokens` | QR | Runtime | Both |
| `attendance_regularization_requests` | Regularize | Runtime | Both |
| `marks_formulas` | CIA formulas | `expression`, `components`, scope cols | Admin, professor |
| `internal_marks` | Saved internals | `marks_data`, `computed_total` | Professor, student |
| `documents` / `document_chunks` | Notes / embeddings | `doc_type`, `is_published` | Ask AI materials; chunks optional |
| `notifications` | Inbox | `type`, `is_read`, `meta` | All roles |
| `announcements` | Circulars | dept-scoped | HOD, student lists |
| `academic_events` | Calendar | `event_type` | Student calendar, timeline |
| `expense_categories` / `expenses` | Finance | amount, date, category | Admin |
| `budgets` | Schema only | fiscal year | **Unused in UI** |
| `feature_flags` / `institution_features` | Toggles | `code`, `is_enabled` | Admin |
| `ai_prompt_templates` | Prompt catalog | `system_prompt` | AI |
| `ai_generations` | AI audit | module, tokens, status | Analytics |
| `ai_chats` / `ai_chat_messages` | Ask AI | `subject_id` | Student |
| `compliance_alerts` | Dashboard list | `is_resolved` | Rarely written |
| `activity_logs` | Audit | login/logout, actions | Auth |
| `app_settings` | Key/value | JSON | Expandable |
| `admin_hod_announcements` | Admin→HOD | Runtime | Admin, HOD |
| `professor_announcements` | Prof→students | Runtime | Professor, student |
| `professor_hod_messages` | Complaints | Runtime | Professor, HOD |

Foreign keys in `schema.sql` are limited (e.g. `departments` → `institutions`, `users` → `institutions`). Many relations are enforced in PHP, not MySQL FKs.

---

# 8. Project Structure

```
professor/
├── index.php                      Front controller
├── .htaccess                      Rewrite all missing paths to index.php
├── install.php                    Web installer (schema + seed + demo users)
├── install_cli.php                CLI wrapper around install.php
├── routes/web.php                 Route table
├── config/
│   ├── config.php                 App, DB, Gemini, notifications
│   └── config.local.php.example   Hosting overrides (copy to config.local.php)
├── app/
│   ├── bootstrap.php
│   ├── Core/                      App, Router, Controller, Model, View, Autoloader
│   ├── Controllers/               Admin, Auth, Hod, Professor, Student, Api, Legacy
│   ├── Models/
│   ├── Services/                  NavService, FinanceExpensePdf, ProfessorDashboardInsights
│   └── Views/                     Layouts + role/admin templates
├── includes/                      Auth, Database, helpers, AI, tools, layout
├── admin/                         Redirect stubs to MVC
├── hod/                           Legacy HOD pages
├── professor/                     Legacy professor pages
├── student/                       Legacy student pages
├── api/ai.php                     AI orchestrator (also used by AiController)
├── share/plan.php                 Public approved-plan link
├── auth/                          login.php / logout.php (compat)
├── assets/css|js|img
├── database/
│   ├── schema.sql
│   ├── seed.sql
│   ├── seed_e2e_test_data.php
│   ├── reset_dev_data.php
│   └── reset_dev_data.sql
└── uploads/                       Gitignored; assignments, message attachments, PPTX
```

| Path | Role |
|------|------|
| `includes/helpers.php` | URLs, CSRF, academic context, HOD/professor/student rules |
| `includes/layout.php` | Legacy page chrome + nav |
| `includes/Gemini.php` | Gemini HTTP client + demo generators |
| `*Tools.php` | Feature-specific logic and runtime schema |

---

# 9. Authentication and Authorization

### Login / logout / session

1. `GET /login` — form (`app/Views/auth/login.php`)
2. `POST /login` — CSRF + `Auth::attempt()`
3. Lookup `users` by email, `password_verify()` against `password_hash` (bcrypt)
4. Inactive users fail with a specific message
5. `last_login_at` updated; password hash stripped from session
6. Redirect to role dashboard
7. Session name: `ppai_session` (config)
8. `GET /logout` — destroy session, redirect `/`

Home `/` shows the marketing landing page if logged out, or the role dashboard if logged in.

### Role checks (backend)

| Mechanism | Behaviour |
|-----------|-----------|
| `Auth::requireLogin()` | Redirect to login |
| `Auth::requireRole(...)` | 403 + flash + dashboard redirect |
| `require_admin_perm()` | Admin permission catalog |
| `LegacyController` | Whitelist of page slugs only |
| HOD helpers | Department id from session |
| Professor helpers | `subject_assignments` |
| Student helpers | Own class + current catalog |
| Message downloads | Role + ownership on the announcement row |

Frontend nav hiding is **not** sufficient. Do not remove these PHP checks.

### Isolation summary

| Actor | Must stay inside |
|-------|------------------|
| All | `institution_id` |
| HOD | Own `department_id` |
| Professor | Assigned class+subject |
| Student | Own class + current year/semester subjects; own history contexts |
| College Admin | Own institution (not other colleges) |

---

# 10. AI Features

**Provider:** Google **Gemini** (`includes/Gemini.php`).  
**Default model:** `gemini-2.5-flash` (retired models remap to this).  
**Endpoint:** `https://generativelanguage.googleapis.com/v1beta`  
**Embed model in config:** `text-embedding-004` (schema supports `document_chunks.embedding_json`; student Ask AI grounds on **text materials**, not a separate vector search UI).

**Configuration:** `config.php` → `gemini.api_key` or env `GEMINI_API_KEY`. Use `YOUR_API_KEY_HERE`. Never put keys in JavaScript.

**Entry:** `POST /api/ai?module=...` → `AiController` (login required, JSON 401 if not signed in).

### Modules

| Module | Roles | Writes |
|--------|-------|--------|
| `syllabus_extract` | professor, admin, superadmin | Returns text only |
| `course_plan` | professor, admin, hod | `course_plans`, `plan_units`, versions |
| `bloom` | professor, hod, admin | `bloom_data` |
| `review` | professor, hod, admin | `ai_score`, `ai_review` |
| `improve` | professor, admin | New plan version |
| `lesson` | professor, admin | `lesson_plans` |
| `questions` | professor, admin | banks + questions |
| `ppt` | professor, admin | `presentations` |
| `assignment` | professor, admin | `assignments` |
| `formula` | admin, hod, professor | Returns JSON (caller saves formula) |
| `ask_ai` | student, professor, admin | `ai_chats` |

Prompts are seeded in `ai_prompt_templates`.

### Fallback without API key

Demo JSON/text generators in `Gemini.php` (course plan, questions, PPT, assignment, formula, Bloom, review). Student Ask AI uses **offline course-material matching**, not a generic chatbot. Professor Ask AI without a key returns 503.

If Gemini is configured but output is unusable (too few slides, bad question bank), several modules fall back to demo builders.

### Usage tracking

Every module calls `log_ai()` → `ai_generations` (institution, user, module, payloads, model, latency, success/error). Admin Analytics sums these rows.

---

# 11. File Management

| Kind | Location / mechanism | Auth |
|------|----------------------|------|
| Assignment uploads | `uploads/assignments/asg_{userId}_{time}.{ext}` | Student submit; professor grade view |
| Professor → student files | `uploads/professor-messages/` | `/api/messages/attachment` |
| Professor ↔ HOD files | `uploads/professor-hod-messages/` | `source=professor_hod` |
| Admin → HOD files | Admin-hod upload dir via tools | `source=admin_hod` |
| Syllabus PDF/DOCX | Extracted in memory; **not stored** | Professor generate-plan |
| PPTX | Generated to temp/`uploads` then download | Professor or authorized student |
| Question paper / bank PDF | Streamed `SimplePdf` | Professor |
| Course plan share | Public token URL | Approved only |
| NAAC PDF | Streamed | Admin / HOD |
| Finance PDF | `FinanceExpensePdf` | Admin |
| Mark statement PDF | Professor marks page | Professor |
| Attendance monthly PDF | Attendance tools | Professor |
| ICS | Lesson planner | Professor |

Limits: config `upload_max_mb` (default 10). Allowed message types: PDF and DOCX.

`uploads/` is gitignored.

---

# 12. Notifications and Messaging

```
Admin  → all HODs          admin_hod_announcements + notifications
HOD    → department profs  announcements + notifications
Professor → current class  professor_announcements + notifications
Professor ↔ HOD            professor_hod_messages
System → user              notify_user() (approvals, shortage, deadlines, AI)
```

**Storage:** `notifications` (`user_id`, `type`, `title`, `body`, `action_url`, `is_read`, `meta`).

**Recipients:**

- Admin→HOD: all active HODs in the institution
- HOD circular: all active professors in the department
- Professor→student: students matching the selected course context
- Professor→HOD: the active HOD of that department

**Display:** role `/notifications` pages. Types include approval, system, ai, announcement. Priority high/medium/low. Read/unread and delete are implemented.

**Attachments:** authorized download only; raw filesystem paths are not exposed as public URLs.

HOD unread count **includes** admin_hod messages; other roles exclude `meta.kind = admin_hod_message`.

---

# 13. Finance

See [Finance](#finance) under College Admin.

Summary: add-only expenses, hardcoded categories, optional department, monthly/yearly totals, year archives, top category, monthly/yearly PDF. No expense edit/delete. No budget UI. No automatic year-on-year chart beyond archive panels.

---

# 14. Reports

| Report | Route / trigger | Format |
|--------|-----------------|--------|
| Admin NAAC snapshot | `/admin/naac?download=pdf` | PDF |
| HOD NAAC pack | `/hod/reports?download=pdf` | PDF |
| Finance statement | `/admin/finance/pdf?scope=month\|year` | PDF |
| Question bank | Professor questions `download=pdf` | PDF (no answers) |
| Question paper + key | `/professor/question-paper` | PDF |
| Course plan export / bulk pack | plan-export / plans | HTML / PDF |
| PPTX / PPT PDF / handout | professor/student ppt-* | PPTX, PDF |
| Mark statement | `/professor/marks?download=mark_statement` | PDF |
| Attendance export | Attendance page | CSV, PDF |
| User import template | `/admin/users?export=template` | CSV |
| Shared plan | `/share/plan.php` | HTML |
| Lesson ICS | Lesson planner | ICS |

No Excel-native writer for finance. User import is CSV despite some “Excel” wording.

---

# 15. Important Business Rules

1. Users belong to one institution (`users.institution_id`).
2. Departments belong to one institution.
3. HODs belong to one department; `departments.hod_user_id` tracks the HOD user.
4. Professors belong to a department but **teach only via assignments**.
5. Students belong to a department and a class; year and semester live on the user row (with class fallback).
6. Courses belong to department + year (`meta`) + Odd/Even semester.
7. Professor assignments determine teaching responsibility and student “professor name”.
8. Students see courses from the **current** academic context, not every enrollment ever.
9. Academic history must be preserved — do not delete attendance/marks/submissions on promotion.
10. Current semester lists must not mix previous-semester subjects (filter by catalog + class + year + semester).
11. Professors may only access assigned course/class pairs.
12. Students may only access their own records and owned history contexts.
13. HODs may only mutate their department’s subjects, assignments, and approvals.
14. College Admin may only see their institution.
15. Subject codes are unique per institution; HODs cannot steal another department’s code.
16. Class year must match course year on assignment.
17. Auto-enrollment only touches students who **currently** match the course context.
18. Course plan share links work only for **approved** plans.
19. Question-bank UI hides answers; papers/keys may show them.
20. Marks formulas use a safe parser — never `eval()`.
21. Feature flags hide nav; do not treat them as the only authorization layer.
22. Protected bootstrap admin account must not be locked out from the Users screen.
23. CSRF is required on state-changing POSTs.
24. Gemini keys and notification provider keys stay server-side.

---

# 16. Recommended Testing Flow

Only steps the application actually supports:

1. Log in as College Admin.
2. Open Institution — set name, academic year, current semester, branding if needed.
3. Create a department (e.g. CSE).
4. On Users, create a class: UG, Year 1, Section A.
5. Create an HOD for CSE.
6. Create a professor in CSE.
7. Create a student in CSE, Year 1, Odd, class Section A.
8. Log in as HOD → Courses → create DBMS (Year 1, Odd, theory).
9. Assign the professor + Year 1 Section A (students auto-enroll if they match).
10. Log in as professor — confirm only that course/class.
11. Generate a course plan (paste or upload syllabus) and submit.
12. HOD Approvals — approve or return with feedback.
13. Professor — generate lesson sessions; optionally add to calendar.
14. Generate question bank items; confirm answers are hidden in the bank.
15. Build a question paper and download PDF / answer key.
16. Generate PPT; download PPTX/PDF.
17. Create and publish an assignment.
18. Log in as student — My Courses shows DBMS and the professor name (or Not Assigned if step 9 skipped).
19. Student sees the assignment and can submit text/file.
20. Professor marks attendance (manual and/or QR).
21. Student checks attendance %.
22. Professor enters CIA and saves internals (attendance/assignment pull-through).
23. Student checks Internal Marks.
24. Professor Message Students (optional PDF/DOCX).
25. Student notifications — download attachment.
26. Admin changes student semester Odd → Even.
27. Student current courses change to Even catalog.
28. Academic History still lists Odd activity if records exist.
29. Admin Finance — add expense, download PDF.
30. Admin Analytics — confirm AI and people counts.
31. Admin NAAC Builder and HOD NAAC Reports — download PDFs.
32. Admin Notifications — message all HODs.
33. Optional: Feature Flags — hide a module and confirm nav changes.

---

# 17. Developer Handover Notes

- Do not break role checks or institution isolation.
- Do not mix current catalog queries with historical rows.
- Do not hardcode professor–student–course links; use `subject_assignments` and academic context helpers.
- Preserve session auth (`ppai_session`); this is not a JWT/React app.
- Preserve `/api/ai` module names and JSON `{ ok, error }` shape.
- Preserve student “current vs history” behaviour when changing year/semester.
- Changing `subjects.meta` year/semester changes **who sees the course now**.
- Changing database unique keys (enrollments, marks, attendance sessions) can silently drop history.
- After schema changes, test Admin, HOD, Professor, and Student.
- Feature flags are nav-only on many legacy pages.
- `programs` and `budgets` and `password_resets` exist in schema but have little or no UI.
- HOD “Complaints” is messaging, not statutory compliance.
- Billing is display-only.
- NAAC Builder is a snapshot, not a full SSR product.
- PPT narration and Google Slides are unfinished.
- Dashboard `compliance_alerts` are rarely produced.
- Institution page has unused class-backend support; class UI is on Users.
- Admin notifications page hides the personal inbox.
- Currency symbol on Admin Dashboard (`$`) does not match Finance (`₹`).
- Prefer `config.local.php` for hosting; do not commit secrets.

---

# 18. Local Development Setup

### Required software

- **XAMPP** (or Apache + MySQL/MariaDB + PHP)
- **Apache** with `mod_rewrite` and `AllowOverride All` for this folder
- **MySQL 8+** or **MariaDB 10.5+**
- **PHP 8.1+** (the code uses `declare(strict_types=1)`, `match`, union types)
- PHP extensions: `pdo_mysql`, `json`, `mbstring`, `openssl`, `zip` (DOCX extract / PPTX)

### Database

| Setting | Typical local value |
|---------|---------------------|
| Database name | `proprofessor` |
| Host | `127.0.0.1` |
| Port | Set in `config/config.php` or `config.local.php` (XAMPP is often `3306`; this repo’s sample config may use another local port) |
| User | Your MySQL user |
| Password | `YOUR_DB_PASSWORD` |

Copy `config/config.local.php.example` → `config/config.local.php` for overrides. `config.local.php` is gitignored.

### Install

1. Start Apache and MySQL in XAMPP.
2. Visit `http://localhost/professor/install.php` (adjust folder name if different).
3. Submit the form. The installer creates the database if permitted, runs `database/schema.sql` and `database/seed.sql`, and creates demo users.
4. Optional Gemini key field on the installer — or set `GEMINI_API_KEY` / `gemini.api_key` to `YOUR_API_KEY_HERE`.
5. Open `http://localhost/professor/login`.
6. On production hosts, remove or rename `install.php` after success.

CLI: `php install_cli.php` (optional first argument is a Gemini key).

### URLs

| URL | Purpose |
|-----|---------|
| `/` | Landing (logged out) or dashboard |
| `/login` | Login |
| `/logout` | Logout |
| `/admin/dashboard` | College Admin |
| `/hod/dashboard` | HOD |
| `/professor/dashboard` | Professor |
| `/student/dashboard` | Student |

Pretty URLs need rewrite. If they 404, open `index.php` / `install.php` and fix `AllowOverride`.

`base_url` defaults to `auto` (detects `/professor` or `/demo/professor`).

### Upload directories

Ensure the web server can write `uploads/` (and subfolders created by message/assignment tools).

### AI

Without a key, demo generators still run for most professor tools. Student Ask AI uses offline course context. Do not commit real API keys.

### Optional CLI data tools

```bash
php database/reset_dev_data.php --confirm-local-reset
php database/seed_e2e_test_data.php --confirm-local-reset
```

These wipe/reseed **local** data. Do not run on production.

### Demo accounts

The installer prints demo emails on the success screen. Use the password shown there (defined in `install.php`). Do not publish production passwords in documentation.

---

# 19. Page / Feature Map

| Role | Page / feature | Purpose |
|------|----------------|---------|
| All | Landing `/` | Marketing home when logged out |
| All | Login / Logout | Session auth |
| Admin | Dashboard | Users, plans, students, all-time expenses |
| Admin | Institution | College profile, add department, read-only course catalog |
| Admin | Users & Roles | HOD/professor/student/admin CRUD, classes, CSV import |
| Admin | Feature Flags | Per-institution module toggles |
| Admin | Marks Formulas | Scoped CIA formulas (+ HOD may open URL) |
| Admin | Finance | Add expenses, archives, PDF |
| Admin | NAAC Builder | Course-plan snapshot + PDF |
| Admin | Analytics | AI usage, people, expenses by department |
| Admin | Subscription | Tier/seats display only |
| Admin | Notifications | Broadcast to all HODs + attachment |
| HOD | Dashboard | Approvals, faculty, AI score, alerts |
| HOD | Approvals | Course plan review |
| HOD | Faculty | Professor stats + circulars |
| HOD | Students | Read-only roster + filters |
| HOD | Courses | Catalog + professor assignment |
| HOD | Analytics | Department charts |
| HOD | Complaints | Professor message inbox (`/hod/compliance`) |
| HOD | Timeline | Events + plan dates |
| HOD | NAAC Reports | Department PDF |
| HOD | Notifications | Personal inbox |
| Professor | Dashboard | Plans + insights widgets |
| Professor | New Course Plan | AI/manual plan + syllabus upload |
| Professor | My Plans | Status, versions, share, export |
| Professor | Plan view/compare/export | Detail tools |
| Professor | Lesson Planner | Sessions, status, ICS |
| Professor | Question Bank | Generate, similarity, bank PDF |
| Professor | Question Paper | Sets, PDF, answer key |
| Professor | PPT Generator | Slides, PPTX/PDF/handout |
| Professor | Assignments | Publish, grade, extensions, push to marks |
| Professor | Attendance | Manual, QR, import, regularization |
| Professor | Internal Marks | Formula, pull-through, What-If, PDF |
| Professor | Message Students | Class message + files |
| Professor | Message HOD | Department thread |
| Professor | Settings | Profile + notification prefs |
| Professor | Notifications | Inbox |
| Public | Share plan | Read-only approved plan |
| Student | Dashboard | Current academics |
| Student | My Courses | Current subjects/labs + professor |
| Student | Course PPT | View/download current decks |
| Student | Assignments | Submit current work |
| Student | Ask AI | Subject-grounded Q&A |
| Student | Calendar | Lesson sessions (read-only) |
| Student | Attendance | Current subjects + QR |
| Student | Internal Marks | Current subjects |
| Student | Academic History | Previous year/semester records |
| Student | Notifications | Messages and downloads |

---

## Architecture reminder

```
Institution
    ↓
College Admin
    ↓
Department  →  HOD
    ↓
Course + Class assignment  →  Professor
    ↓
Student current context  →  Current portal
    ↓
Preserved rows  →  Academic History
```

This application is an existing working PHP + MySQL system. Extend it by following the relationships and helpers above. Do not invent parallel course or enrollment models.

---

# REAL-WORLD COLLEGE CUSTOMER WORKFLOW

This section describes how a **real college would use ProProfessor after deciding to adopt it**, mapped to the **current PHP + MySQL implementation**.

It is written for developers and implementers who need to walk a college through:

```
COLLEGE PURCHASES PROPROFESSOR
        ↓
COLLEGE ACCOUNT / INSTITUTION
        ↓
COLLEGE ADMIN
        ↓
DEPARTMENT
        ↓
HOD
        ↓
PROFESSOR
        ↓
STUDENT
        ↓
DAILY ACADEMIC USAGE
```

Anything that a typical SaaS sale would include but **this codebase does not implement** is labelled **NOT CURRENTLY IMPLEMENTED**.

---

## 1. College purchases the application

### What actually happens today

ProProfessor is **not** a self-serve SaaS checkout. A college does **not** create itself by paying on the public landing page.

| Expected commercial step | Current implementation |
|--------------------------|------------------------|
| Online purchase / payment gateway | **NOT CURRENTLY IMPLEMENTED** |
| Automatic college provisioning | **NOT CURRENTLY IMPLEMENTED** |
| Email invitation to College Admin | **NOT CURRENTLY IMPLEMENTED** (no invite mailer on user create) |
| Platform operator creating many colleges from a console | **NOT CURRENTLY IMPLEMENTED** (no Platform Admin UI) |
| Subscription expiry lockout | **NOT CURRENTLY IMPLEMENTED** |
| Renewal / upgrade payment | **NOT CURRENTLY IMPLEMENTED** |

**How a college actually gets an environment:**

1. The product is installed on a server (XAMPP locally, or Apache + MySQL hosting).
2. Someone runs `install.php` (or `install_cli.php`).
3. Installer runs `database/schema.sql` + `database/seed.sql`.
4. One **institution** row is created (seed example: Madurai Demo Arts & Science College).
5. One **College Admin** user is created and linked with `users.institution_id`.
6. Admin logs in at `/login` with the email/password shown on the install success screen.

The public landing page (`/`) “Get Started” and “Login” buttons both go to **`/login`**. There is no cart, Razorpay/Stripe, or signup form.

### Institution / customer record

- Stored as one row in `institutions`.
- The College Admin’s `users.institution_id` points at that row.
- Queries across the app filter by this `institution_id` (institution isolation).

A second college would require **another install**, **another database**, or a **manually inserted** second `institutions` row plus admin user. There is no in-app “add college” wizard.

### Subscription / plan (display only)

Admin → **Subscription** (`/admin/billing`) shows:

| Field | Source |
|-------|--------|
| Plan | `institutions.subscription_tier` (`starter` / `professional` / `enterprise` / `trial`) |
| Licensed seats | `institutions.licensed_seats` |
| Seats in use | Count of active-looking users with role professor, HOD, or admin |

A static **tier comparison table** (Starter / Professional / Enterprise with rupee prices) is **marketing copy on the page**. Changing tier or seats is done in the **database** (or seed scripts), not by paying in the UI.

### Subscription limits

| Limit | Enforced in application code? |
|-------|-------------------------------|
| Licensed seats | **NOT CURRENTLY IMPLEMENTED** — seats are displayed; creating extra users is not blocked |
| Feature access by tier | **NOT CURRENTLY IMPLEMENTED** — modules use **feature flags**, not `subscription_tier` |
| Login blocked when subscription expires | **NOT CURRENTLY IMPLEMENTED** — there is no expiry date column used by `Auth` |
| Institution `is_active` blocking login | **NOT CURRENTLY IMPLEMENTED** — login checks **user** `is_active` only |

### When the “subscription” is active

If the institution row exists and the admin user is active, **the full application works** (subject to feature flags). The billing page has no effect on teaching, students, or AI.

### When the subscription “expires”

**NOT CURRENTLY IMPLEMENTED.** Nothing in `Auth::attempt()` reads `subscription_tier` or an expiry date. The college would keep working until an operator deactivates users or takes the site down.

### Renewal flow

**NOT CURRENTLY IMPLEMENTED.**

### Development / demo note

Installer + seed + optional `database/seed_e2e_test_data.php` is the **demo onboarding path**. Treat Subscription as a **catalog display**, not a billing product.

---

## 2. College Admin first login

```
College is given a deployed ProProfessor instance
        ↓
College Admin receives email + password from the installer / operator
        ↓
Admin opens /login
        ↓
Session created (ppai_session)
        ↓
Redirect to /admin/dashboard
        ↓
Admin completes Institution setup
        ↓
Admin creates classes, departments, HODs, professors, students
```

After login, Admin should typically:

1. Open **Institution** — college name, affiliation, NAAC grade, academic year, current semester, city/state, logo URL, brand colours, attendance minimum, optional QR geofence.
2. Add **departments** (name + code).
3. Open **Users & Roles** — create **classes** (UG/PG, year 1–4, section, class name), then HOD / professor / student accounts.
4. Optionally set **Marks Formulas**, **Feature Flags**, **Finance**, review **Subscription** (read-only).

There is **no first-login wizard** and **no email that the app sends** when the admin account is created. Credentials are communicated **out of band** (install screen, operator, or Admin telling staff the password).

Admin can later **reset other users’ passwords** (min 8 characters) from Users. The protected bootstrap admin cannot have role/password changed from that screen.

---

## 3. Institution setup

Admin → **Institution** (`/admin/institution`).

### Setup options that exist

| Setting | Stored | Used later in |
|---------|--------|----------------|
| College name | `institutions.name` | Dashboards, NAAC PDFs, question-paper/PPT letterhead via branding helpers |
| University affiliation | `affiliation_university` | NAAC Admin PDF, HOD NAAC PDF |
| NAAC grade | `naac_grade` | NAAC PDFs |
| Academic year | `academic_year` (e.g. 2025-26) | Copied onto `subject_assignments` / enrollments; optional marks year snapshot |
| Current semester | `current_semester` | Fallback if a student has no personal `users.semester` |
| City / state | `city`, `state` | NAAC PDF address line |
| Logo URL | `logo_url` | PPT branding (`PresentationTools`) |
| Brand colours | `settings.brand_*` | PPT, some PDFs (finance uses brand colours) |
| Attendance minimum % | `settings.attendance_min` (default 75) | Shortage alerts, student attendance colour bands |
| QR geofence | lat/lng/radius + required flag | Student QR check-in distance check |

**UG/PG** is **not** an institution-wide toggle. It is stored per **class** as `classes.meta.level` (`UG` or `PG`) when Admin creates a class on **Users**.

**Not on the Institution form** (may still exist in DB): postal address, pincode, phone, email, NBA status, subscription fields.

### Where institution data becomes visible

| Area | What students/professors see |
|------|------------------------------|
| Student pages | Academic year/semester **context comes from the student user + class**, not a live “college name” banner on every page. Institution name is not the student’s primary academic filter. |
| Professor pages | Institution branding on PPT; attendance min for shortage; assigned courses only |
| Question papers | Institution letterhead via question-bank PDF tools |
| PPTs | Logo + brand colours snapshot on `presentations.meta.branding` |
| Finance PDF | College name + brand colours |
| Admin NAAC PDF | Name, address parts, NAAC grade, affiliation, year, semester, NBA status |
| HOD NAAC PDF | Same letterhead + **department** snapshot |
| Login / landing | Product name “ProProfessor AI”, not the college name |

---

## 4. Admin creates departments

Example college after rename on Institution: **ABC College of Engineering**.

Admin adds departments (name + code only):

```
ABC College of Engineering
        ↓
College Admin → Institution → Add department
        ↓
CSE  |  ECE  |  EEE  | Mechanical  | Civil
```

```
College Admin
        ↓
Creates Department (name + code)
        ↓
Row in departments (institution_id set automatically)
        ↓
Users form can assign HOD / professor / student to that department
```

**Implemented:** create.  
**NOT CURRENTLY IMPLEMENTED:** edit department name/code, delete/deactivate department from the UI.

### Department isolation (actual)

| Role | Rule |
|------|------|
| HOD | `users.department_id` must match; `hod_save_subject` / approvals / faculty / students filter that id |
| Professor | Account has a department; **teaching access** is `subject_assignments` (still department-checked when HOD assigns) |
| Student | `users.department_id` + class in that department; course catalog is `subjects` for that department |
| CSE HOD | Cannot load ECE subjects or ECE students through HOD helpers that require `hod_department_id()` |
| Admin | Institution-wide; some HOD pages allow `?department_id=` for admin |

A CSE HOD **must not** see another department’s private academic data on HOD screens that enforce `department_id`. Direct URL tampering is rejected or ignored on those queries.

---

## 5. Admin creates HOD

```
Admin → Users & Roles
        ↓
Role = HOD
        ↓
Select Department (required) e.g. CSE
        ↓
Name + email + password
        ↓
users row (role=hod, department_id=CSE)
        ↓
departments.hod_user_id updated
        ↓
HOD logs in → /hod/dashboard
        ↓
Manages that department only
```

### What the HOD can actually access

| Can | Cannot |
|-----|--------|
| Department dashboard, approvals, faculty circulars | Create professor/student **accounts** |
| Department students (read-only, filters) | Create **classes** / sections |
| Create/update **courses** and assign professors | Edit Institution / Finance / Users |
| Review course plans (approve / return / comments) | See another department’s HOD queue (when logged in as HOD) |
| Department analytics, NAAC PDF, timeline | Faculty page is HOD-only (admin 403) |
| Complaints inbox (professor messages) | |
| Personal notifications | |

**Authorization:** `Auth::requireRole('hod'|'admin')` on most HOD pages; mutations use `hod_department_id()`. Faculty page is **`hod` only**.

CSE HOD vs ECE: different `department_id`. Subject create/assign validates subject, professor, and class all belong to that department.

---

## 6. Admin creates professors

```
Admin → Users & Roles
        ↓
Role = Professor
        ↓
Assign Department (required) e.g. CSE
        ↓
Account created (email + password set by Admin)
        ↓
Professor logs in → /professor/dashboard
        ↓
Sees NO courses until HOD assigns subject + class
        ↓
HOD → Courses → Assign professor + year/section class
```

**NOT CURRENTLY IMPLEMENTED:** automatic email “you’ve been invited.” Admin (or the college) must tell the professor how to log in.

### Relationship

```
Professor (users.department_id = CSE)
        ↓
Does not teach by department alone
        ↓
HOD creates Course (subjects: year in meta, Odd/Even semester)
        ↓
HOD assigns: professor + class (year + section)
        ↓
subject_assignments (course × professor × class × academic_year)
```

| Dimension | Where it lives |
|-----------|----------------|
| Department | User + subject + class |
| Course | `subjects` |
| Year | Course `meta.year` and class `year` (must match on assign) |
| Section | `classes.section` via `class_id` |
| Semester | Course `subjects.semester` (Odd/Even) |

Until assignment exists, the professor’s academic tools have **no class/course pairs**.

---

## 7. Admin creates students

Example:

| Field | Value |
|-------|--------|
| Name | Mohammed Abuthahir |
| Department | CSE |
| Program (UG/PG) | Chosen as **class** level `UG` (not a separate student field) |
| Year | 1st Year → `academic_year_level = 1` |
| Section | A → `class_id` of CSE Year 1 Sec A |
| Semester | Odd → `users.semester = Odd Semester` |

```
Student account created
        ↓
Student logs in → /student/dashboard
        ↓
Auth::refresh() loads latest year/semester/class
        ↓
Current academic context:
  Department CSE + Year 1 + Section A + Odd Semester
  UG/PG from the class row (shown on Attendance / Calendar)
        ↓
courses_for_student()
  → HOD catalog: CSE + year 1 + Odd
        ↓
For each subject + student's class_id
  → subject_assignments → professor name
  → else UI: "Not Assigned" (My Courses / Dashboard)
     or "Professor Not Assigned" (Attendance page)
```

**Matching logic (implemented):** department + year + Odd/Even semester on the **subject catalog**, then professor for **that subject + that class**. Empty professor is not invented.

---

## 8. HOD setup (after the account exists)

Example: **CSE HOD Dr. Kumar**

```
CSE HOD logs in
        ↓
Creates/manages courses (code, name, credits, hours, syllabus, year, Odd/Even, theory/lab)
        ↓
Academic context of a course = year + semester on the subject (not a separate “term wizard”)
        ↓
Assigns professors to class/section
        ↓
Matching students auto-enrolled (enrollments) if year/semester/class match
        ↓
Reviews course plans (Approvals)
        ↓
Monitors department (Dashboard, Analytics, Timeline)
        ↓
Views students (read-only)
        ↓
Faculty circulars
        ↓
NAAC department PDF
        ↓
Complaints / professor messages
```

HOD does **not** set institution academic year (Admin does). HOD does **not** promote students to the next year (Admin edits the student).

---

## 9. Course creation and professor assignment

Real-world example for **CSE / Year 1 / Section A / Odd Semester**:

HOD creates (year = 1, semester = Odd):

- Engineering Mathematics I
- Programming in C
- Digital Principles
- Web Technologies
- Communication Skills

HOD assigns for **Year 1 Section A**:

| Course | Professor |
|--------|-----------|
| Engineering Mathematics I | Arun Kumar |
| Web Technologies | Divya Kumar |
| (DBMS, if created) | Arun Kumar |
| Communication Skills | *(none yet)* |

Student Mohammed (CSE, Year 1, Sec A, Odd) then sees those Odd Year-1 catalog rows. Assigned courses show **Arun Kumar** / **Divya Kumar**. Unassigned courses show **Not Assigned** / **Professor Not Assigned** (label depends on the page).

If HOD never assigns a professor, students still see the **course title** from the department catalog (when year+semester match); they do **not** get a fake professor name.

---

## 10. Professor daily usage

Example: **Professor Arun Kumar** after HOD assignment.

```
Logs in → /professor/dashboard
        ↓
Sees assigned course/class pairs only
        ↓
Selects a course (e.g. Engineering Mathematics I · Year 1 Sec A)
        ↓
New Course Plan / My Plans (AI or upload syllabus PDF/DOCX)
        ↓
HOD approval workflow
        ↓
Lesson Planner (sessions, complete/delay, optional calendar ICS)
        ↓
Teaches class (offline — the app does not take live classroom video)
        ↓
Attendance (manual and/or QR)
        ↓
Assignments (create, publish, grade, extensions)
        ↓
Internal Marks (CIA + auto attendance/assignment pull-through)
        ↓
Question Bank → Question Paper PDF (answers on key, not in bank list)
        ↓
PPT Generator (PPTX/PDF/handout)
        ↓
Message Students (optional PDF/DOCX)
        ↓
Message HOD if needed
```

Roster for attendance/marks/messages = students whose **current** year, semester, class, and department match that course (`students_for_current_course_context`).

---

## 11. Student daily usage

```
Student logs in
        ↓
Current Academic Context (read-only)
  UG (from class) · CSE · Year 1 · Section A · Odd Semester
        ↓
My Subjects / My Labs
  Engineering Mathematics I — Arun Kumar
  Web Technologies — Divya Kumar
  Communication Skills — Not Assigned
        ↓
Available modules (implemented):
  Assignments · Attendance · Internal Marks
  Course PPT · Calendar (lesson sessions)
  Notifications / professor files
  Ask AI (must pick a current subject)
  Academic History (empty until past activity exists)
```

Student **cannot** change year, section, or semester. Calendar is view-only.

---

## 12. Semester change scenario

**This is how the live application behaves.**

```
START
  Student: Year 1 · Section A · Odd Semester
  Arun teaches Engineering Mathematics I (Odd, Year 1, Sec A)
  Arun records attendance, assignment, internal marks
        │
        │  College Admin → Users → edit student
        │  semester: Odd  →  Even
        │  (class_id usually stays Section A unless Admin also changes class)
        ▼
CURRENT PORTAL
  Context: Year 1 · Section A · Even Semester
  My Courses = HOD catalog for CSE Year 1 EVEN only
  Arun does NOT appear unless he is assigned to an Even course
  for this class
        ▼
ACADEMIC HISTORY
  Year 1 Odd (if attendance/marks/assignments exist)
  Engineering Mathematics I + those records
```

```
          Odd semester                         Even semester
     ┌─────────────────────┐              ┌─────────────────────┐
     │ Current view        │   Admin      │ Current view        │
     │ Maths · Arun        │ ──────────►  │ Even catalog only   │
     │ Attendance / marks  │  semester    │ Arun gone unless    │
     │ Assignments         │  Odd→Even    │ newly assigned      │
     └──────────┬──────────┘              └─────────────────────┘
                │
                ▼
     Academic History keeps Odd Maths records
     (rows NOT deleted)
```

Professor Arun’s **current** attendance list for Odd Maths **drops students** whose semester no longer matches that course. He does not “lose” the old session rows; they remain in `attendance_records` / `internal_marks` / submissions.

---

## 13. Year change scenario

```
Year 1  →  Year 2  →  Year 3  →  Year 4
```

There is **no automatic promotion batch**. Admin edits the student:

- `academic_year_level` = 2
- usually a **new class_id** (Year 2 Section A) so year on class matches (Users validation requires class year = student year)

```
After Admin sets Year = 2
        ↓
Current portal: Year 2 + current section + current semester
        ↓
My Courses: CSE catalog where subject meta.year = 2 and semester matches
        ↓
HOD must already have Year 2 courses + professor assignments
        ↓
Year 1 attendance, marks, assignments, subjects
        ↓
Academic History (not the current Attendance/Marks pages)
```

**NOT CURRENTLY IMPLEMENTED:** one-click “promote entire section to Year 2.”

---

## 14. Professor change scenario

```
Year 1 Odd: Mathematics → Arun Kumar
Arun records attendance / marks / assignments
        ↓
Semester (or year) changes; Arun is not assigned to the student's
current course+class
        ↓
Arun's current roster for that old pair no longer includes the student
        ↓
Old records remain on the old class_id + subject_id
        ↓
If HOD later assigns Arun to e.g. Year 2 Odd DBMS for that student's new class
        ↓
Student appears again on Arun's current lists for THAT assignment only
```

Implementation: `professor_can_manage_subject` + `student_matches_course_context`. Visibility is **assignment + current student context**, not “once a student, always a student.”

Removing a `subject_assignments` row does **not** delete historical attendance/marks.

---

## 15. Multiple departments

```
ABC College of Engineering  (one institutions row)
    ├── CSE  → CSE HOD → CSE professors → CSE students
    ├── ECE  → ECE HOD → ECE professors → ECE students
    ├── EEE
    └── Mechanical
```

| Isolation | How |
|-----------|-----|
| CSE HOD | `department_id` on every HOD query |
| ECE students | Not in CSE HOD student list |
| CSE professor | Cannot be assigned ECE class (HOD assign checks professor department) |
| Admin | Sees all departments in the **same institution** |
| Another college | Different `institution_id` (or separate install) — **not** selectable in this UI |

Cross-department data leak is prevented by PHP/SQL filters, not only by hiding menu items.

---

## 16. Complete customer lifecycle

```
COLLEGE PURCHASES PROPROFESSOR
        │  (operator deploys + install.php — no in-app checkout)
        ▼
  INSTITUTION row + COLLEGE ADMIN user
        ▼
  COLLEGE ADMIN logs in
        ▼
  INSTITUTION SETUP (name, year, semester, branding, attendance min)
        ▼
  CREATE DEPARTMENTS
        ▼
  CREATE CLASSES (UG/PG, year, section) on Users
        ▼
  CREATE HOD (per department)
        ▼
  CREATE PROFESSORS
        ▼
  CREATE STUDENTS (dept, class, year, semester)
        ▼
  HOD CONFIGURATION (course catalog)
        ▼
  COURSE ASSIGNMENTS (professor + class → enrollments)
        ▼
  PROFESSOR TEACHING WORKFLOW
 ┌──────────────────────────────┐
 │ Course Plan                  │
 │ Lesson Planner               │
 │ Attendance                   │
 │ Assignments                  │
 │ Internal Marks               │
 │ Question Bank                │
 │ Question Paper               │
 │ PPT                          │
 │ Student Communication        │
 └──────────────────────────────┘
        ▼
  STUDENT LEARNING (current catalog)
        ▼
  CURRENT ACADEMIC RECORDS
        ▼
  SEMESTER CHANGE (Admin edits student)
        ▼
  ACADEMIC HISTORY (Odd/previous activity)
        ▼
  YEAR PROGRESSION (Admin edits year + class)
```

---

## 17. Who does what?

| Activity | College Admin | HOD | Professor | Student |
|----------|:-------------:|:---:|:---------:|:-------:|
| Institution setup | Yes | No | No | No |
| Department create | Yes | No | No | No |
| Create HOD account | Yes | No | No | No |
| Create professor account | Yes | No | No | No |
| Create student account | Yes | No | No | No |
| Create class / section / UG-PG | Yes | No | No | No |
| Create course / lab | No | **Yes** | No | No |
| Assign professor to course+class | No | **Yes** | No | No |
| Course plan create/submit | No | Review only | **Yes** | No |
| Course plan approve/return | No | **Yes** | Submit only | No |
| Lesson plan | No | No | **Yes** | Sees calendar sessions |
| Attendance mark | No | No | **Yes** | View + QR + regularize request |
| Assignment publish | No | No | **Yes** | Submit |
| Internal marks entry | Formulas only | Formulas URL possible | **Yes** | View current |
| Question bank / paper | No | No | **Yes** | No |
| PPT generate | No | No | **Yes** | View/download current |
| Notifications (inbox) | Yes (HOD broadcast UI) | Yes | Yes | Yes |
| Message HODs (all) | **Yes** | Receive | No | No |
| Message professors (circular) | No | **Yes** | Receive | Dept announcements list |
| Message students | No | No | **Yes** | Receive / download |
| Message HOD (thread) | No | Reply (Complaints) | **Yes** | No |
| Finance | **Yes** | No | No | No |
| Analytics | Institution | Department | Dashboard insights | No |
| NAAC snapshot PDF | Institution | Department | Plan export | No |
| Academic history | Changes student context | No | No | **View past** |
| Feature flags | **Yes** | No | No | No |
| Subscription display | View only | No | No | No |

---

## 18. Real college example (fictional)

**College:** ABC College of Engineering  
**Department:** CSE  
**HOD:** Dr. Kumar  
**Professor:** Arun Kumar  
**Student:** Mohammed Abuthahir  

**Do not use real production passwords.** Admin sets passwords in Users; installer prints demo credentials on a private install screen.

### Journey

1. Operator installs ProProfessor; College Admin logs in; renames institution to ABC College of Engineering; sets academic year and Odd semester.
2. Admin adds department **CSE**.
3. Admin creates class: UG, Year 1, name e.g. `I CSE`, section **A**.
4. Admin creates HOD **Dr. Kumar** (CSE), professor **Arun Kumar** (CSE), student **Mohammed Abuthahir** (CSE, class Year 1 A, year 1, Odd).
5. Dr. Kumar creates Odd Year-1 courses including Engineering Mathematics I; assigns **Arun Kumar** + Year 1 Sec A.
6. Mohammed logs in: sees Mathematics with **Arun Kumar**; can open assignments/attendance/marks/PPT/Ask AI once Arun publishes them.
7. Arun generates a course plan; Dr. Kumar approves; Arun takes attendance and CIA marks.
8. Admin sets Mohammed’s semester to **Even**. Mohammed’s current My Courses switch to Even Year-1 CSE catalog. Arun disappears from current view unless assigned to an Even course. Odd Mathematics remains under **Academic History**.
9. Later Admin sets year to **2** and a Year-2 class. Year-1 records stay in history. Dr. Kumar assigns Year-2 courses and professors independently.

---

## 19. Current implementation vs future business

### Currently implemented

- One institution per typical install; data scoped by `institution_id`
- College Admin login and full admin modules (institution, users, classes, formulas, finance, NAAC snapshot, analytics, feature flags, HOD messaging)
- Department create; HOD/professor/student accounts; student year + Odd/Even
- HOD course catalog, professor–class assignment, auto-enrollment of **matching** students, plan approval
- Professor teaching suite (plans, lessons, QB, papers, PPT, attendance, marks, assignments, messaging)
- Student current portal + Academic History from real activity
- Semester/year change by **Admin editing the student** (no data wipe)
- Department isolation for HOD; assignment isolation for professors
- Gemini AI with offline/demo fallbacks
- Subscription **display** of tier and seats

### Future / business workflow not yet implemented

Only items that are **actually missing** from this repository:

- Real **payment gateway** / checkout
- **Automatic college provisioning** after purchase
- **Email invitation** / welcome mail when Admin creates users
- In-app **subscription billing**, upgrade, invoice, GST
- **Seat-limit enforcement** from `licensed_seats`
- **Subscription expiry** that locks login or modules
- **Renewal** workflow
- **Forgot password** email (`password_resets` table unused by Auth)
- Production hosting itself (this is an app; hosting is an operations concern)

Landing-page “Get Started” is **not** a purchase flow; it is a **login link**.

---

## 20. Final purpose — How ProProfessor is used by a college

A college does not buy a seat inside a global consumer app in this codebase. An operator **installs** ProProfessor and creates an **institution** plus a **College Admin**.

That admin **configures** the college, **creates departments**, and **creates people**. **HODs** own the course catalog and who teaches which class. **Professors** run teaching, assessment, and communication for **assigned** courses only. **Students** see **current** year/section/semester content and keep **history** when Admin moves them to Even semester or the next year.

Academic administration, teaching, assessment, and student learning stay on **one PHP + MySQL platform**, isolated per institution and department, without deleting past attendance, marks, or assignments.





