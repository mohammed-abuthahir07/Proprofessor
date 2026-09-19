# ProProfessor AI
# Complete Application Demo & Testing Documentation

---

## 1. Purpose of This Document

This document is a **step-by-step demo and testing guide** for the complete ProProfessor AI application.

**Who should use it**

- Developers onboarding to the project
- QA / manual testers
- Client or demo presenters
- Anyone who has never used the application before

**What will be tested**

- Admin institution and user setup
- HOD department academic setup and Course Plan approval
- Professor AI tools and academic operations
- Student portal consumption of academic data
- AI Provider (BYOK) configuration and generation gates
- Professor-side PDF / export outputs

**Complete application flow (recommended order)**

```
ADMIN
  → Institution / College setup
  → Department
  → HOD user
  → Classes, Professors, Students
HOD
  → Courses + Professor assignment
PROFESSOR
  → AI Provider connect
  → Course Plan → submit → HOD review
  → Lesson Planner, Question Bank, PPT, Assignments
  → Attendance, Internal Marks, Messages
STUDENT
  → Courses, PPT, Assignments, Attendance, Marks, Ask AI
```

Always follow this order. Academic modules depend on Admin/HOD setup first.

---

## 2. Application Overview

**ProProfessor AI** is an India-focused academic operating system built as a **PHP + MySQL** web application (not Node.js / React).

It helps a college manage:

- Institution structure (departments, classes, users)
- Department courses and professor–class assignments (HOD)
- Course Plans with HOD approval
- Lesson plans, question banks, PPT decks, assignments
- Attendance and internal marks
- Student portal scoped to each student’s class and courses
- Optional AI (OpenAI / Gemini / Claude) using each **Professor’s own API key**

### Major roles

| Role | Who they are | Main job |
|------|----------------|----------|
| **Admin** | College administrator | Institution profile, departments, users, classes, formulas, finance, NAAC tools |
| **HOD** | Head of Department | Courses, faculty/student view, Course Plan approvals, dept analytics |
| **Professor** | Teaching faculty | Plans, AI tools, attendance, marks, messaging |
| **Student** | Enrolled learner | View courses, PPT, assignments, attendance, marks, Ask AI |

Schema also allows `superadmin`, but the Admin **Users & Roles** UI creates only `admin`, `hod`, `professor`, and `student`.

### High-level relationship

```
Admin
  ↓
Institution / College
  ↓
Department
  ↓
HOD (linked to department)
  ↓
Professor (assigned courses by HOD)
  ↓
Student (in a class; enrolled in subjects)
```

### Isolation rules (must respect during demos)

- Data is isolated per **institution** (`institution_id`).
- HOD sees their **department**.
- Professor sees **assigned** class/subject combinations.
- Student sees **own class** and **enrolled** courses.

Do not expect cross-institution or cross-department data to appear.

---

## 3. Before Starting the Demo

### Required software

- **XAMPP** (Apache + MySQL/MariaDB + PHP)
- A modern browser (Chrome / Edge / Firefox)

### Project location (typical local)

```
C:\xampp\htdocs\professor
```

### Database (local defaults from `config/config.php`)

| Setting | Typical local value |
|---------|---------------------|
| Host | `127.0.0.1` |
| Port | `3307` (confirm in your `config` / `config.local.php`) |
| Database name | `proprofessor` |
| User | `root` |
| Password | as configured locally |

Optional override file: `config/config.local.php`.

### Start services

1. Open **XAMPP Control Panel**.
2. Start **Apache**.
3. Start **MySQL**.
4. Confirm the database `proprofessor` exists and schema is loaded (`database/schema.sql` or via installer).

### Confirm the application is running

1. Open: `http://localhost/professor/login`  
   (If your folder name differs, adjust the path; `base_url` can be `auto`.)
2. You should see the ProProfessor AI login page.
3. Pretty URLs use `.htaccess` → `index.php`.

### Installer (optional first-time setup)

- Web installer: `install.php`
- Creates schema + basic demo users when used successfully.

### AI configuration note

- **Professors** use **Bring Your Own Key (BYOK)** in **Settings → AI Provider** (OpenAI / Gemini / Claude).
- Server-side Gemini config may still exist in `config.php` for other/legacy paths.
- For Professor demos of Course Plan / Lesson / Questions / PPT / Assignment generation, **connect a Professor API key first**.

### Optional local CLI helpers (data only — not required to demo)

| Script | Purpose |
|--------|---------|
| `php database/reset_dev_data.php --confirm-local-reset` | Wipe non-admin test data; keep College Admin |
| `php database/seed_e2e_test_data.php --confirm-local-reset` | Reset + seed multi-dept demo users |

These scripts refuse non-local DB hosts and require confirmation flags.

---

## 4. LOGIN / ACCESS OVERVIEW

### Shared login

| Item | Value |
|------|--------|
| URL | `/login` (example: `http://localhost/professor/login`) |
| Fields | Email + Password |
| Auth | PHP session + bcrypt (`password_verify`) |

After login, each role is redirected to their dashboard.

### Who creates whom

```
ADMIN
  ↓ Creates / edits Institution
  ↓ Creates Department(s)
  ↓ Creates HOD user (links to department)
  ↓ Creates Class(es)
  ↓ Creates Professor user(s)
  ↓ Creates Student user(s) (assign class)
HOD
  ↓ Creates Courses (subjects)
  ↓ Assigns Professor + Class + academic year
PROFESSOR
  ↓ Uses assigned courses for AI/academic work
STUDENT
  ↓ Sees enrolled / class-scoped content
```

**Important:** Admin does **not** create department courses. HOD creates courses under **Courses** (`/hod/subjects`).

There is **no** public self-registration.

### DEMO / TEST ACCOUNTS

Use only accounts that exist in your database.

#### After web installer (`install.php`) — DEMO / TEST ACCOUNT

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@proprofessor.local` | `Password@123` |
| HOD | `hod@proprofessor.local` | `Password@123` |
| Professor | `professor@proprofessor.local` | `Password@123` |
| Student | `student@proprofessor.local` | `Password@123` |

#### After E2E seed script — DEMO / TEST ACCOUNT

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@proprofessor.local` | **Unchanged** (installer password if that was used) |
| Dummy HOD/Professor/Student accounts | e.g. `csehod@test.com`, … | `Test@12345` |

#### After a clean Admin-only data reset

Only College Admin remains.  
**Create HOD / Professor / Student during the Admin demo steps below.**  
Do not invent passwords in documentation — use what you set when creating users.

---

# PART 1 — ADMIN DEMO

## 5. Admin Login

1. Open `http://localhost/professor/login`.
2. Enter Admin email (DEMO: `admin@proprofessor.local`).
3. Enter Admin password.
4. Click login.
5. Confirm redirect to **Admin Dashboard** (`/admin/dashboard`).
6. Sidebar should show **Admin View**.

**Expected:** Institution overview widgets; navigation groups MAIN / OPERATIONS / GROWTH.

---

## 6. Admin Dashboard

**Open:** `/admin/dashboard`

**What to verify**

- Overview of the college institution
- Quick access via sidebar

**Main navigation (actual labels)**

| Group | Items |
|-------|--------|
| MAIN | Dashboard, Institution, Users & Roles |
| OPERATIONS | Feature Flags, Marks Formulas, Finance |
| GROWTH | NAAC Builder, Analytics, Subscription, Notifications |

---

## 7. Institution / College Setup

**Open:** `/admin/institution` (sidebar → **Institution**)

### Steps

1. Review / edit college profile fields (name, address, academic year, semester, branding as present on the form).
2. Save institution details.
3. Use **Add department** (on this page) if creating a new department here.
4. Verify the institution name appears correctly after save.

**Expected:** Institution row remains; Professors later see this name on letterheads / PDF headers where applicable.

**Note:** Subscription / seats live on the institution record and are also visible under **Subscription** (`/admin/billing`).

---

## 8. College Admin / Administrative Setup

Admin users are managed under **Users & Roles**.

**Open:** `/admin/users`

### Capabilities (actual)

- Create / update users
- Toggle active
- Reset password
- CSV import (if shown)
- **Add class** (UG/PG, year, section) for academic groups

### Create a second Admin (optional)

1. Open **Users & Roles**.
2. Create user with role **admin**.
3. Log out and log in as that user to verify.

**Permissions:** Feature Flags and some modules may require admin permissions configured in the app. For demos, use the primary College Admin account.

---

## 9. Department Setup

Departments can be created from **Institution** (**Add department**) and appear linked to the college.

### Steps

1. Open **Institution**.
2. Add a department (DEMO DATA example name: `Computer Science and Engineering`).
3. Save.
4. Verify the department appears in the institution/department list.

**Expected:** Department is available when creating HOD and when HOD manages courses.

---

## 10. HOD Setup

**Open:** `/admin/users` → create user with role **hod**

### Steps

1. Enter full name, email, password.
2. Set role to **hod**.
3. Assign the **department**.
4. Save.
5. Confirm `departments.hod_user_id` linkage is applied by the app when creating/updating HOD (HOD becomes head of that department).
6. Log out.
7. Log in as the HOD.
8. Confirm **HOD Dashboard** (`/hod/dashboard`) and **HOD View** chrome.

### Also create (before Professor demo)

| Entity | Where | Notes |
|--------|--------|------|
| **Class** | Users & Roles → **Add class** | Department, year (1–4), section, UG/PG as form allows |
| **Professor** | Users & Roles → role `professor` | Assign department |
| **Student** | Users & Roles → role `student` | Assign **class** + register number |

**DEMO DATA (examples only — replace with your values):**

| Field | Sample |
|-------|--------|
| HOD email | `cse.hod@college.local` |
| Professor email | `prof.cse@college.local` |
| Student email | `student1@college.local` |
| Class | CSE · UG · Year 1 · Section A |

---

# PART 2 — HOD DEMO

## 11. HOD Login

1. Open `/login`.
2. Enter HOD credentials.
3. Confirm redirect to `/hod/dashboard`.
4. Confirm department context (department-scoped lists).

---

## 12. HOD Dashboard

**Open:** `/hod/dashboard`

**Verify**

- Department-focused summary
- Navigation to Approvals, Faculty, Students, Courses

**HOD navigation (actual)**

| Group | Items |
|-------|--------|
| MAIN | Dashboard, Approvals, Faculty, Students, Courses |
| INSIGHTS | Analytics, Complaints, Timeline |
| REPORTS | NAAC Reports, Notifications |

---

## 13. Professor Management

**Open:** `/hod/faculty` (**Faculty**)

### Steps

1. Open Faculty list.
2. Confirm Professors belonging to the department appear (accounts were created by Admin).
3. Use circular / announcement features if shown on the page.
4. Do **not** expect full user CRUD here — account creation is Admin.

---

## 14. Student Management

**Open:** `/hod/students` (**Students**)

### Steps

1. Open Department Students.
2. Confirm students are filtered to the HOD’s department / related classes.
3. Use any filters provided on the page (year/section if present).

**Expected:** Only department-relevant students; not other institutions.

---

## 15. Courses

**Open:** `/hod/subjects` (**Courses**)

This is where department **subjects/courses** are created and Professors are assigned.

### Steps

1. Create a course (code, name, credits, syllabus fields as shown).
2. Save.
3. Assign a **Professor** + **Class** + academic year (assignment UI on this page).
4. Verify the assignment appears.

**Expected:** Professor later sees this course in Course Plan / Lesson / PPT / Attendance selectors.

---

## 16. Course Plan Review

**This is a core workflow.**

### Statuses (database)

`draft` → `submitted` → `under_review` → `approved` **or** `returned`

UI filter label **Rejected** maps to status `returned`. There is no separate `rejected` status.

### End-to-end steps

```
Professor generates/saves Course Plan (draft)
        ↓
Professor clicks Submit (My Plans)
        ↓
Status = submitted
        ↓
HOD opens Approvals
        ↓
HOD Save comments → under_review (optional)
        ↓
HOD Approve → approved
   OR Request changes / Return → returned
        ↓
Professor edits & re-Submits from draft / returned / under_review
```

### HOD tester steps

1. Log in as HOD.
2. Open **Approvals** (`/hod/approvals`).
3. Open a plan with status **submitted** (or pending).
4. Read plan content.
5. Optionally save comments (**Save comments** → moves toward review).
6. Click **Approve** **or** **Request changes**.
7. Confirm status updates.

**Rules**

- HOD cannot approve a **draft** — Professor must submit first.
- Approved plans become eligible for NAAC/NBA export on Professor **My Plans** / plan view.

---

## 17. HOD Analytics / Reports / Other Modules

| Module | Path | What to do | Expected |
|--------|------|------------|----------|
| Analytics | `/hod/analytics` | Open Department Analytics | Charts/stats for dept |
| Complaints | `/hod/compliance` | Open message threads with Professors | Conversations / attachments as stored |
| Timeline | `/hod/timeline` | Open Timeline Tracker | Activity timeline for dept |
| NAAC Reports | `/hod/reports` | Generate/view NAAC/NBA oriented reports | PDF/report output as implemented |
| Notifications | `/hod/notifications` | Open notification feed | Department/user notifications |

For each: open → observe data → click primary actions → confirm no cross-department leakage.

---

# PART 3 — PROFESSOR DEMO

## 18. Professor Login

1. Open `/login`.
2. Enter Professor credentials.
3. Confirm `/professor/dashboard` and **Professor View**.
4. Confirm only **assigned** courses appear in academic selectors.

---

## 19. Professor Dashboard

**Open:** `/professor/dashboard`

**Verify**

- Assigned course summaries / widgets (as implemented)
- Notifications indicator
- Sidebar access to AI tools and academic modules

**Professor navigation (actual)**

| Group | Items |
|-------|--------|
| MAIN | Dashboard, New Course Plan, My Plans |
| AI TOOLS | Lesson Planner, Question Bank, PPT Generator |
| ACADEMIC | Assignments, Attendance, Internal Marks, Message Students, Message HOD, Settings, Notifications |

Linked pages (not always in sidebar): plan view/compare/export, PPT view/download/handout, question paper.

---

## 20. Professor Settings

**Open:** `/professor/settings`

### Profile

1. Edit full name, phone, password (optional).
2. Save.
3. Confirm values persist after reload.

### Delivery Preferences

1. Review email notification / digest / channel options.
2. Save preferences.
3. Confirm flash success.

### AI Provider

Section id: `#ai-provider`

| Control | Purpose |
|---------|---------|
| Provider select | OpenAI / Gemini / Claude |
| Model select | Current models from `config/ai_models.php` |
| Custom model field | Optional override (validated) |
| API key | Professor’s personal key (masked when connected) |
| **Test Connection** | Live ping without necessarily saving |
| **Connect AI** | Test + store encrypted key; one active provider |
| **Remove Connection** | Deletes stored connection; keeps existing academic content |

### How to get an API key

On Settings, open **How to get an API key?** help card. Official docs links (from catalog):

| Provider | Key portal (docs URL in app) |
|----------|------------------------------|
| OpenAI | https://platform.openai.com/api-keys |
| Gemini | https://aistudio.google.com/apikey |
| Claude | https://console.anthropic.com/settings/keys |

**Never paste real production keys into shared documentation.**

---

## 21. AI Provider Setup

### Correct sequence

1. Open **Settings** → AI Provider.
2. Select provider (OpenAI / Gemini / Claude).
3. Select a **current** model from the dropdown (or validated custom id).
4. Paste personal API key.
5. Click **Test Connection** → expect success message.
6. Click **Connect AI** → badge **Connected**.
7. Return to AI modules (Course Plan, Lesson Planner, etc.).

### Generation requirements

Professor AI generation is **blocked** until:

- Provider selected **and**
- Model selected **and**
- API key stored **and**
- Connection established (Connect AI after successful test)

### Expected messages if incomplete

| Situation | Expected behavior |
|-----------|-------------------|
| No provider / not connected | Block; message to configure Settings; option to open AI Settings |
| No model | Block; select model in Settings |
| No API key | Block; add and connect key |
| Key entered but not connected | Block; Test + Connect required |
| Invalid key | Connect/Test fails; generation blocked |
| Remove Connection | New generation blocked; old plans/PPT/etc. remain |

Frontend shows **Generating…** only after the connection meta check passes; backend still validates (`ProfessorAiSettings::requireForGeneration`).

---

## 22. Course Plan Generation

**Open:** `/professor/generate-plan` (**New Course Plan**)

### Steps

1. Confirm AI Provider is **Connected**.
2. Select assigned course / enter subject, credits, university, syllabus (paste or upload PDF/DOCX for text extract).
3. Choose accreditation template if shown.
4. Click **Generate**.
5. Review learning outcomes, units, Bloom distribution, etc.
6. Save / continue to plan view as UI directs.
7. Open **My Plans** (`/professor/plans`).
8. **Submit** the plan to HOD.
9. Confirm status **submitted**.
10. Switch to HOD → **Approvals** → Approve or Request changes.
11. If returned, Professor edits and re-submits.
12. Confirm final status **approved**.

**Syllabus file extract** is local text extraction (not an AI generate call) and may work without BYOK; **Generate Course Plan** requires connected AI for Professors.

---

## 23. Lesson Planner

**Open:** `/professor/lessons` (**Lesson Planner**)

### Steps

1. Confirm AI connected.
2. Select an approved / available course plan as required by the form.
3. Generate session-by-session lesson plans.
4. Review titles, duration, methods, activities.
5. Save/use as implemented.
6. Use **Add to Calendar** if present (downloads `.ics`).

**Expected:** Sessions derived from course plan units/hours; AI failure shows clear error (no silent demo content for Professors).

---

## 24. Question Bank

**Open:** `/professor/questions` (**Question Bank Generator**)

### Steps

1. Confirm AI connected.
2. Select plan/subject, unit, Bloom level, type, count as form requires.
3. Generate questions.
4. Review stems, options, marks, Bloom, CLO, difficulty.
5. Use duplicate/similarity warnings if shown.
6. Save bank / questions.
7. Build paper / Generate Sets A·B·C if buttons present.
8. Click **Download PDF**.
9. Open PDF and verify academic letterhead layout.

Related: `/professor/question-paper` for paper view when linked from the module.

---

## 25. PPT Generator

**Open:** `/professor/ppt` (**PPT Generator**)

### Steps

1. Confirm AI connected.
2. Enter title, select plan/context.
3. Click **Generate PPT**.
4. Open deck from **Generated decks**.
5. Review slides + speaker notes.
6. Download **PPTX** (primary export).
7. Optional: **Student Handout** from deck view (PDF handout).

**Note:** List page exposes **Open** + **PPTX**. Deck PDF endpoint may exist for compatibility; primary professor download for demos is **PPTX**.

Students can view shared decks under **Course PPT**.

---

## 26. Assignment Generator

**Open:** `/professor/assignments` (**Assignment Module**)

### Steps

1. Confirm AI connected.
2. Select assigned course/class.
3. Enter type / context / requirements.
4. Generate assignment (title, description, rubric, max marks).
5. Review and save.
6. Publish / assign to class as UI allows.
7. Later: review submissions / AI grading assist if available (also requires AI connection).

---

## 27. Attendance

**Open:** `/professor/attendance`

### Steps

1. Select class + subject + date/month as required.
2. Start session / mark Present, Absent, Late, Excused.
3. Save.
4. Use QR features / CSV import-export if shown.
5. Click **Export PDF**.
6. Verify attendance report tables and percentages.

**Expected:** Only roster for assigned class/subject; percentages match stored session logic.

---

## 28. Internal Marks

**Open:** `/professor/marks`

### Steps

1. Select class + subject (+ academic year if filtered).
2. Enter component marks per student.
3. Save.
4. Confirm computed totals/grades follow Admin **Marks Formulas**.
5. Download **Generate Mark Statement** (PDF).
6. Verify statement header (institution, course, professor) and table.

Do not change formula logic during demos — only enter marks and observe calculations.

---

## 29. Messaging / Notifications

| Feature | Path | Steps |
|---------|------|-------|
| Message Students | `/professor/messages` | Select recipients/class → compose → optional PDF/DOCX attach → send |
| Message HOD | `/professor/message-hod` | Compose to department HOD → send |
| Notifications | `/professor/notifications` | Open feed → mark read as UI allows |

HOD-side threads also appear under HOD **Complaints** (`/hod/compliance`).

---

## 30. Professor PDF / Export Testing

For each export below: generate content → click export → open file → verify layout.

| Module | Action | Verify |
|--------|--------|--------|
| My Plans | **Export Selected** (NAAC/NBA package) | Multi-plan PDF; course data; footer branding |
| Plan view | **Export NAAC** / **Export NBA** (approved) | Same academic content as plan |
| Question Bank | **Download PDF** | Letterhead, Q numbering, options, no clip |
| PPT | **PPTX** (+ optional Student Handout PDF) | Deck download works; handout if used |
| Attendance | **Export PDF** | Sessions + % + day-wise grid |
| Marks | **Mark Statement** PDF | Components + final + grade |
| Lessons | **Add to Calendar** `.ics` | Opens in calendar app |

**PDF quality checklist**

- [ ] Opens successfully
- [ ] Professional header / letterhead
- [ ] Footer with ProProfessor AI / professor / datetime / page numbers (where implemented)
- [ ] Tables readable; headers repeat across pages where implemented
- [ ] No UI buttons inside PDF
- [ ] Correct institution & course (tenant-safe)
- [ ] Academic content matches on-screen data

---

# PART 4 — STUDENT DEMO

## 31. Student Login

1. Open `/login`.
2. Enter Student credentials.
3. Confirm `/student/dashboard` and **Student View**.

---

## 32. Student Dashboard

**Open:** `/student/dashboard`

**Verify** class-scoped overview and links into courses / assignments / attendance.

**Student navigation (actual)**

| Group | Items |
|-------|--------|
| MAIN | Dashboard, My Courses, Course PPT |
| LEARNING | Assignments, Ask AI, Calendar |
| ACADEMIC | Attendance, Internal Marks, Academic History, Notifications |

QR check-in helper may exist at `/student/attendance-qr` (not always in sidebar).

---

## 33. Student Academic Information

| Module | Path | Student sees | Data origin |
|--------|------|--------------|-------------|
| My Courses | `/student/courses` | Enrolled / class courses | Enrollments + HOD subjects |
| Course PPT | `/student/notes` | Shared lecture decks | Professor presentations |
| Assignments | `/student/assignments` | Assigned work / submit | Professor assignments |
| Ask AI | `/student/ask-ai` | Course-scoped Q&A | Student AI path (not Professor BYOK UI) |
| Calendar | `/student/calendar` | Academic calendar events | Institution/academic events |
| Attendance | `/student/attendance` | Own attendance % | Professor sessions |
| Internal Marks | `/student/marks` | Own marks | Professor internal marks |
| Academic History | `/student/academic-history` | Prior academic context | History tools |
| Notifications | `/student/notifications` | Alerts | Notification service |

For each: open → confirm only **this student’s** data → no other class/institution leakage.

---

# PART 5 — COMPLETE END-TO-END DEMO

## 34. Complete Academic Workflow

Use this as a single rehearsal script.

| Step | Actor | Action |
|------|-------|--------|
| 1 | Admin | Login; verify Institution |
| 2 | Admin | Create Department |
| 3 | Admin | Create Class |
| 4 | Admin | Create HOD (link department) |
| 5 | Admin | Create Professor |
| 6 | Admin | Create Student (assign class) |
| 7 | HOD | Login |
| 8 | HOD | Create Course; assign Professor + Class |
| 9 | Professor | Login |
| 10 | Professor | Settings → select provider + model + API key → Test → **Connect AI** |
| 11 | Professor | New Course Plan → Generate → Save |
| 12 | Professor | My Plans → **Submit** |
| 13 | HOD | Approvals → review → **Approve** (or Request changes → Professor resubmits) |
| 14 | Professor | Lesson Planner → generate sessions |
| 15 | Professor | Question Bank → generate → Download PDF |
| 16 | Professor | PPT Generator → generate → Download PPTX |
| 17 | Professor | Assignments → generate → save |
| 18 | Professor | Attendance → mark session → Export PDF |
| 19 | Professor | Internal Marks → enter → Mark Statement PDF |
| 20 | Student | Login → My Courses / PPT / Assignments / Attendance / Marks |

---

# PART 6 — AI PROVIDER TESTING

## 35. AI Provider Test Matrix

Perform from Professor account on Settings + any Generate button.

| Test | Provider | Model | API Key | Connected | Expected |
|------|----------|-------|---------|-----------|----------|
| 1 | None | — | — | No | Blocked; configure Settings |
| 2 | Gemini | Not selected | Present | No | Blocked; select model |
| 3 | Gemini | Selected | Empty | No | Blocked; add & connect key |
| 4 | OpenAI | Selected | Entered | Not connected | Blocked; Test + Connect |
| 5 | Any | Selected | Invalid | Fail test | Connect fails; generation blocked |
| 6 | OpenAI | Current catalog model | Valid | Yes | Real OpenAI generation |
| 7 | Gemini | Current catalog model | Valid | Yes | Real Gemini generation |
| 8 | Claude | Current catalog model | Valid | Yes | Real Claude generation |
| 9 | Any | Retired / invalid model | Valid | — | Model unavailable error |
| 10 | — | — | — | Remove Connection | New generation blocked |
| 11 | Change model | New model | Same/new key | Re-connect | New gens use new model |
| 12 | Change provider | Gemini→OpenAI | New key | Re-connect | New gens use OpenAI |

Current model lists live in `config/ai_models.php` (update when providers retire IDs).

---

## 36. IMPORTANT: EXISTING CONTENT AFTER AI CHANGE

| Action | Effect on existing Course Plans / Lessons / Questions / PPT / Assignments |
|--------|-----------------------------------------------------------------------------|
| Change Gemini → OpenAI | **Unchanged** |
| Change model A → model B | **Unchanged** |
| Remove Connection | **Unchanged**; only **new** AI generation blocked |
| Reconnect another provider | **Unchanged**; **new** generation uses new provider |

Never expect old academic records to regenerate or delete when AI settings change.

---

## 37. ROLE SECURITY TESTING

| Check | Expected |
|-------|----------|
| Professor A opens Professor B’s plan by id | Access denied / not found |
| Professor from Institution A | Cannot see Institution B data |
| HOD CSE | Does not manage ECE department courses |
| Student | Sees only own class enrollments / marks / attendance |
| Admin | Retains Admin dashboard after reset |
| Manual API call `/api/ai?...` without BYOK (Professor) | HTTP 422 structured error |

Do not attempt production attacks or destructive bypass techniques.

---

## 38. FINAL DEMO CHECKLIST

### Admin

- [ ] Admin login
- [ ] Institution verified / saved
- [ ] Department created
- [ ] Class created
- [ ] HOD user created & login works
- [ ] Professor user created
- [ ] Student user created
- [ ] Feature Flags / Formulas / Finance / NAAC / Analytics / Subscription smoke-checked (as needed)

### HOD

- [ ] HOD login
- [ ] Dashboard
- [ ] Faculty list
- [ ] Students list
- [ ] Course created
- [ ] Professor assigned to course + class
- [ ] Course Plan Approvals
- [ ] Approve / Request changes workflow
- [ ] Analytics / Complaints / Timeline / NAAC Reports / Notifications

### Professor

- [ ] Login
- [ ] Dashboard (assigned courses only)
- [ ] Settings profile + delivery prefs
- [ ] AI provider + model + API key
- [ ] Test Connection
- [ ] Connect AI
- [ ] Course Plan generate + submit
- [ ] Lesson Planner
- [ ] Question Bank (+ PDF)
- [ ] PPT (+ PPTX)
- [ ] Assignment
- [ ] Attendance (+ PDF)
- [ ] Internal Marks (+ statement PDF)
- [ ] Message Students / Message HOD
- [ ] Notifications
- [ ] Remove Connection blocks new AI; old content remains

### Student

- [ ] Login
- [ ] Dashboard
- [ ] My Courses
- [ ] Course PPT
- [ ] Assignments
- [ ] Ask AI
- [ ] Calendar
- [ ] Attendance
- [ ] Internal Marks
- [ ] Academic History
- [ ] Notifications

---

## 39. TROUBLESHOOTING

### Cannot login

**Check**

- Apache and MySQL running in XAMPP
- Database `proprofessor` exists
- Email/password correct; user `is_active = 1`
- PHP errors in Apache/PHP log
- Correct URL (`/professor/login` under your vhost/subdir)

### Blank page / 404 on pretty URLs

**Check**

- `mod_rewrite` enabled
- `.htaccess` present
- `AllowOverride` permits rewrites
- Try `index.php` front controller path

### AI generation blocked

**Check**

- Settings shows **Connected**
- Provider + current model selected
- API key valid for that provider
- Billing/quota on provider account
- Browser confirm dialog may offer **Open AI Settings**

### Wrong / empty course lists for Professor

**Check**

- HOD assigned subject + class + academic year
- Professor user `department_id` correct
- Assignment row exists in `subject_assignments`

### Student sees no courses

**Check**

- Student has `class_id`
- Enrollments exist for that class/subject
- Feature flags for student portal enabled if required

### HOD cannot approve plan

**Check**

- Plan status is **submitted** (not draft)
- Plan belongs to HOD’s department
- Feature `hod_approvals` enabled if gated

### PDF does not generate / looks wrong

**Check**

- Required academic data selected (class/subject/bank)
- PHP errors in response
- Library in use is project `SimplePdf` / `ProfessorPdf` (no Composer Dompdf required)
- File downloads with `Content-Type: application/pdf`

### After data reset, only Admin works

**Expected** after `reset_dev_data.php`.  
Recreate HOD/Professor/Student via Admin **Users & Roles**, then HOD **Courses** assignments.

---

## Appendix A — Key URLs (quick reference)

| Role | Login redirect | Important paths |
|------|----------------|-----------------|
| Admin | `/admin/dashboard` | `/admin/institution`, `/admin/users` |
| HOD | `/hod/dashboard` | `/hod/subjects`, `/hod/approvals` |
| Professor | `/professor/dashboard` | `/professor/settings`, `/professor/generate-plan`, `/professor/plans` |
| Student | `/student/dashboard` | `/student/courses`, `/student/notes` |
| Shared | `/login`, `/logout` | |

---

## Appendix B — Technology reminder

- **PHP + MySQL** custom MVC + legacy modules
- Front controller: `index.php`
- Routes: `routes/web.php`
- Auth: session + bcrypt
- Professor AI: BYOK via `ProfessorAiSettings` + `api/ai.php`
- PDF: `includes/SimplePdf.php` + `includes/ProfessorPdf.php`

This documentation reflects the **current application code and navigation**. If a screen label differs slightly after a UI tweak, follow the **sidebar label** in the running app.
