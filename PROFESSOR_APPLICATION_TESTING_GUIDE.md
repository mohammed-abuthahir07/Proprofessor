# ProProfessor AI — Complete Application Testing Guide

## 1. Introduction

**ProProfessor AI** is a college academic operating system. It is a PHP + MySQL web application (XAMPP). It is not a separate Node.js or React app.

This document explains how to **manually test and demonstrate** the application from start to finish. Follow the steps in order. Use the real sidebar names and screens that exist in the product.

The application uses **role-based access**. After login, each person sees only the screens allowed for their role.

The main testing roles are:

| Role | What they do |
|------|----------------|
| **Admin** | College setup, users, fees, salary, finance, exam timetable, announcements |
| **HOD** | Department courses, professor assignment, course-plan approvals, department views |
| **Professor** | Assigned teaching work: course plans, lessons, questions, PPT, assignments, notes, attendance, marks |
| **Student** | Own courses, assignments, materials, calendar/exams, attendance, marks, notices, fees |

Do not expect one role to open another role’s management screens. HOD data is limited to that HOD’s department. Professors only see assigned class/subject work. Students only see their own academic and fee data.

---

## 2. Before Starting

### What you need

- **XAMPP** with **Apache** and **MySQL**
- PHP that XAMPP already provides (no `npm start` and no separate frontend server)
- A browser (Chrome, Edge, or Firefox)
- The project folder, typically: `C:\xampp\htdocs\professor`

### Database (from `config/config.php`)

| Setting | Local value |
|---------|-------------|
| Host | `127.0.0.1` |
| Port | `3307` |
| Database name | `proprofessor` |
| User | `root` |
| Password | empty (unless you changed `config.local.php`) |

### Start the application

This project is served by **Apache**, not a custom backend command.

1. Open **XAMPP Control Panel**.
2. Start **MySQL**.
3. Start **Apache**.
4. Confirm the `proprofessor` database exists.

There is no separate frontend build step. Apache serves the PHP app.

### Open the application

1. Landing page: `http://localhost/professor/`
2. Login page: `http://localhost/professor/login`

You should see **ProProfessor AI** and **Sign in as** with Admin / HOD / Professor / Student.

If your folder name is not `professor`, change the path. `base_url` is usually `auto`.

Pretty URLs go through `.htaccess` → `index.php`.

---

## 3. Login Accounts

Open `http://localhost/professor/login`.

1. Click the matching **Sign in as** option (Admin, HOD, Professor, or Student). That switch is for the login screen only. The account’s real role comes from the database.
2. Enter email and password.
3. Click **Sign In**.

| Role | Email | Password |
|------|-------|----------|
| HOD | csehod@test.com | Password@123 |
| Professor | sandra@gmail.com | Password@123 |
| Student | naveen@gmail.com | Password@123 |

**Admin:** The College Admin account already exists in the application (`admin@proprofessor.local` is the protected College Admin email used by the project). Use the **existing Admin password already set in this installation**. This guide does not list or invent an Admin password.

After a successful login you land on:

- Admin → `/admin/dashboard` (Institution Overview)
- HOD → `/hod/dashboard`
- Professor → `/professor/dashboard`
- Student → `/student/dashboard`

Logout: use **Logout** in the app, or open `/logout`.

If an HOD / Professor / Student login fails, that user may not exist yet. Create them from **Admin → Users & Roles** (section 5) using the emails above, then continue.

---

## 4. Role-Based Testing

## 4.1 Admin Testing

**Login:** choose **Admin**, then the existing College Admin email and password.

The Admin sidebar (actual labels):

**MAIN**

- Dashboard
- Institution
- Departments
- Faculty
- Students
- Exam Timetable
- Fee Collection
- Salary & Payroll
- Users & Roles

**OPERATIONS**

- Feature Flags
- Marks Formulas
- Finance
- Expense

**GROWTH**

- NAAC Builder
- Analytics
- Announcements

### Dashboard

1. Open **Dashboard** (`/admin/dashboard`).
2. Title is **Institution Overview**.
3. Confirm college KPIs and empty or live counts (students, faculty, departments).
4. Expected: page loads with this college’s real data, not demo numbers.

### Institution

1. Open **Institution** (`/admin/institution`).
2. Used to save college profile and add departments.
3. Check/update: College name, affiliation, NAAC grade, attendance minimum %, logo URL, brand colours, academic year, current semester, city, state. Optional QR geofence fields.
4. Click **Save**.
5. Under **Departments**, add a department with **Name** and **Code** (example: Computer Science and Engineering / `CSE`), then **Add department**.
6. Expected: the department appears in the list. **Academic courses** on this page is a read-only catalog of subjects HODs create later.

### Departments

1. Open **Departments** (`/admin/departments`).
2. This is an overview of departments already created (not the create form).
3. View cards: HOD name, students, faculty, plan completion, attendance.
4. Expected: the department you added is listed. HOD shows **Not assigned** until you create an HOD user for that department.

### Faculty

1. Open **Faculty** (`/admin/faculty`).
2. Lists professors (and related faculty) in this college.
3. Filter / export if shown.
4. Expected: after you create a Professor, they appear here. This page does not replace **Users & Roles** for creating accounts.

### Students

1. Open **Students** (`/admin/students`).
2. Lists student accounts in this college.
3. Filter / export if shown.
4. Expected: after you create a Student, they appear here.

### Exam Timetable

See **section 11**. Sidebar: **Exam Timetable**.

### Fee Collection

See **section 8**. Sidebar: **Fee Collection**.

### Salary & Payroll

See **section 9**. Sidebar: **Salary & Payroll**.

### Users & Roles

See **sections 5–7**. Sidebar: **Users & Roles**. Create users, add classes, import CSV.

### Feature Flags

1. Open **Feature Flags** (`/admin/features`).
2. Turn college modules on or off (course plan, lesson planner, question bank, PPT, assignments, attendance, marks, finance, student portal, and similar flags).
3. Expected: turning a flag off can hide that item from the matching role’s sidebar.

### Marks Formulas

1. Open **Marks Formulas** (`/admin/formulas`).
2. Used for internal-marks formula settings used by the college.
3. View or save the formula the institution uses.
4. Expected: save succeeds; later Internal Marks follow this setup.

### Finance

See **section 10**. Sidebar label is **Finance** (`/admin/finance-overview`). Read-only roll-up.

### Expense

See **section 10**. Sidebar label is **Expense** (`/admin/finance`). This is where you add expense rows.

### NAAC Builder

1. Open **NAAC Builder** (`/admin/naac`).
2. Used for NAAC document building from existing college data.
3. Open the page and confirm it loads. Do not expect it to create users or fees.

### Analytics

1. Open **Analytics** (`/admin/analytics`).
2. Institution-level counts and charts from live data.
3. Expected: numbers match users and academic activity you created.

### Announcements

See **section 12**. Sidebar: **Announcements**.

---

## 5. Admin → User Creation Testing

Recommended order (this matches how the app is wired):

```
Admin login
  → Institution (college + academic year / semester)
  → Add Department (Name + Code)
  → Users & Roles → Add class (needed before students)
  → Create HOD (one per department)
  → Create Professor
  → Create Student
  → HOD assigns courses and the professor
  → Then test Professor / Student / HOD workflows
```

### Add a class first (students need it)

On **Users & Roles**, right-side **Add class**:

1. **Department** — required
2. **UG / PG** — required
3. **Year** — 1, 2, 3, or 4
4. **Class name** — example `CSE`
5. **Section** — example `A`
6. Click **Add class**

Expected: the class appears in the student **Class** dropdown as year + UG/PG + section.

### Create users (left-side **Add user**)

Required for every user:

- Full name
- Valid email
- Role

Also required by role:

| Role | Extra required fields |
|------|------------------------|
| HOD | Department |
| Professor | Department |
| Student | Department, Class / section, Academic Year (1st–4th), Semester (Odd or Even) |

Optional on the form: Register No (students), Employee ID, Phone, Password (default on the form is `Password@123`).

**HOD rule implemented in this app:** a department can have **only one active HOD**. A second HOD for the same department is blocked. To replace an HOD, change or deactivate the existing HOD first.

**Create vs import:** **Bulk import (Excel)** on the same page accepts a CSV (`Download Excel template`). Existing emails are skipped. Department code must match (example `CSE`). The one-HOD-per-department rule also applies to import.

---

## 6. Admin → Professor Testing

1. Open **Users & Roles**.
2. Role = **Professor**.
3. Fill the fields that exist on the form:

| Field | Notes |
|-------|--------|
| Full name | Required |
| Email | Required, unique |
| Role | Professor |
| Department | Required |
| Qualification | Shown when role is Professor. Optional (can be left blank). Max 120 characters |
| Employee ID | Optional |
| Phone | Optional |
| Password | Shown on create; default on the form is `Password@123` |

Academic Year / Semester / Class / Register No are **student** fields. They hide or do not apply to Professor.

4. Click **Create**.
5. Open **Faculty** and confirm the professor is listed.
6. Logout and log in as that professor (for this guide: `sandra@gmail.com` / `Password@123`) after HOD has assigned a course.

The professor cannot teach until **HOD → Courses** assigns them a subject + class.

---

## 7. Admin → Student Testing

1. Confirm a **class** exists for the student’s department, year, and UG/PG.
2. Open **Users & Roles** → Role = **Student**.
3. Fill:

| Field | Required | Notes |
|-------|----------|--------|
| Full name | Yes | |
| Email | Yes | |
| Role | Yes | Student |
| Department | Yes | Must match the class department |
| Class (students) | Yes | Year on the class must match Academic Year |
| Academic Year | Yes | 1st / 2nd / 3rd / 4th Year |
| Semester | Yes | Odd or Even |
| Register No | No | Shown later on Fee History |
| Employee ID / Phone | No | |
| Password | On create | Default on the form is `Password@123` |

4. Click **Create**.
5. Open **Students** and confirm the row.
6. The student can log in at `/login` (this guide: `naveen@gmail.com` / `Password@123`).
7. **My Subjects** stays empty until the HOD assigns a professor to that student’s class and subject (that assignment also enrols the class students).

---

## 8. Admin → Fee Collection Testing

**Where:** sidebar **Fee Collection** → `/admin/fee-collection`

**Purpose:** College Admin manually creates fee records per student. Students never create fees.

### Fee types that exist

- **Tuition Fee** (`tuition`)
- **College Bus Fee** (`bus`)
- **Hostel Fee** (`hostel`)

A student does **not** need all three. Only types you add appear for that student.

Examples:

- Student A — Tuition only
- Student B — Tuition + Hostel
- Student C — Tuition + Bus + Hostel

If you never add Bus, the student must **not** see “Bus Fee → Unpaid”.

### What Admin can do

On the list page and **Student Fee Details** (`/admin/fee-collection/student?student_id=…`):

1. Choose student and academic year.
2. **Add** a fee: fee type, total amount (greater than zero), optional due date, optional notes.
3. **Record payment**: amount, payment date, method as the form provides.
4. **Edit** a fee record.
5. **Delete** a fee record.
6. View status: Paid / Partially Paid / Pending (from payments vs total).
7. View pending amount.
8. **Remind** one student or **Remind all** outstanding (creates a student reminder/notice from the implemented reminder action).

### What to verify

1. Add Tuition ₹20,000 for the test student; record ₹10,000 paid.
2. Do **not** add Bus or Hostel.
3. Log in as that student.
4. Dashboard **Fee Status** and **Fee History** must show Tuition only: Total ₹20,000, Paid ₹10,000, Pending ₹10,000, status **Partially Paid**.
5. Bus and Hostel must be absent.

---

## 9. Admin → Salary & Payroll Testing

**Where:** sidebar **Salary & Payroll** → `/admin/salary`

**Purpose:** Monthly faculty salary records and payment status.

Statuses implemented: **Paid**, **Pending**, **On Hold**.

### Workflow

1. Open **Salary & Payroll**.
2. Select the **month** (filter on the page).
3. Faculty members (professors / HODs in this college) appear for payroll.
4. Add or update a salary row: faculty, month, amount, status (and other fields the form shows).
5. One faculty member can have only **one salary record per month**.
6. Open **Salary History** for a faculty member (`/admin/salary/history?faculty_id=…`).
7. Change status to **Paid**.
8. Expected: list and month summary update (processed / pending / on hold). When status becomes Paid, the faculty user can receive a salary notification (implemented notify-on-paid).
9. **Finance** overview later includes paid payroll as expenditure.

Dashboard salary figures come from these records. Do not type fake totals into the dashboard.

---

## 10. Admin → Finance Testing

There are **two** Admin finance screens:

| Sidebar | URL | What it does |
|---------|-----|----------------|
| **Finance** | `/admin/finance-overview` | Read-only overview |
| **Expense** | `/admin/finance` | Create/view college expenses |

### Expense (`/admin/finance`)

1. Open **Expense**.
2. Add an expense using the form on that page (amount, category, department if offered, date/month as shown).
3. Browse by month/year. PDF export exists (`/admin/finance/pdf`) if you need a printout.
4. Expected: the new row appears in the expense list and monthly/yearly totals.

### Finance overview (`/admin/finance-overview`)

This page **does not write data**. It rolls up:

- Fee collection (billed, collected, pending / arrears) for the institution academic year
- Revenue by fee type (Tuition / Bus / Hostel)
- Expense totals by category
- Salary & Payroll **paid** amounts
- Combined expenditure
- Monthly collection view and year archive chips

### How to verify

1. Add a fee payment in **Fee Collection**.
2. Add a **Paid** salary in **Salary & Payroll**.
3. Add an expense in **Expense**.
4. Open **Finance**.
5. Expected: revenue, expenditure, fee, salary, and expense sections move with those records.

---

## 11. Admin → Exam Timetable Testing

**Where:** sidebar **Exam Timetable** → `/admin/exam-timetable`

**Purpose:** Schedule examinations. This is **not** the class/attendance timetable.

Students see rows that match **their** department, year, semester, academic level (UG/PG when the class has a level), and class/section (empty class = all sections of that year).

### Fields on **+ Add Exam**

- Subject / exam name (required)
- Department (required)
- Academic level: UG or PG (required)
- Year (required)
- Semester: Odd or Even (required)
- Class / Section (optional — “All sections of this year”)
- Exam date, start time, end time (required)
- Exam type: End Semester Exam, Internal Exam, Practical, Lab
- Exam hall / room (optional)
- **Lab / practical exam** checkbox

Filters on the list: Department, Academic level (UG & PG / UG only / PG only), Year, Semester, Exam type, Date.

### CRUD

**Create**

1. Click **+ Add Exam**.
2. Example: 1st Year, UG, CSE, Odd, subject Database Management Systems, date, 10:00–13:00.
3. Click **Create Exam**.
4. Expected: row appears in the Admin table.

**Read**

1. Use filters if needed.
2. Expected: the exam stays visible with department, level, year, class/section, times, type.

**Update**

1. Use **Edit** on the row.
2. Change date, time, subject, hall, or type.
3. Expected: the table and the student calendar/countdown use the new values.

**Delete**

1. Delete the row.
2. Expected: it disappears from Admin. Matching students no longer see it.

### Student visibility (must test)

If Admin creates a **1st Year** exam for that department/semester/level:

- A **1st Year** student in that department (same semester/level, and section if the exam is section-scoped) **sees** it on **Calendar** and the dashboard countdown.
- A **2nd Year** student **must not** see it.

---

## 12. Admin → Announcements Testing

**Where:** sidebar **Announcements** → `/admin/notifications`

**Purpose:** College Admin sends notices (optional PDF/DOCX attachment, max 10 MB).

### Audience options that exist

- All HODs
- All Professors
- All Students
- First Year Students
- Second Year Students
- Third Year Students
- Fourth Year Students

Notice types on the form include **Important**, **Academic**, **Event** (and any others shown).

### Steps

1. Open **Announcements**.
2. Enter title and message.
3. Select audience.
4. Optional: attach a file.
5. Submit / send.
6. Expected: if that audience has active users, the announcement is stored and recipients can open it.

### Where recipients see it

| Audience | Where to check |
|----------|----------------|
| All HODs | HOD → **Notifications** |
| All Professors | Professor → **Notifications** |
| All Students / year students | Student → **Notices** |

Year audiences use the student’s academic year (1–4). A 1st Year notice must not appear as a targeted year notice for a 2nd Year student.

---

## 13. HOD Testing

**Login**

- Sign in as **HOD**
- Email: `csehod@test.com`
- Password: `Password@123`

Expected: **HOD View** and `/hod/dashboard`.

HOD sidebar (actual labels):

**MAIN**

- Dashboard
- Approvals
- Faculty
- Students
- Courses

**INSIGHTS**

- Analytics
- Compliance
- Notifications

**SYSTEM**

- Settings

HOD screens are limited to **that HOD’s department** (linked when Admin created the HOD user).

### Dashboard

1. Open **Dashboard**.
2. Department snapshot: faculty, students, pending approvals, compliance-style cards as implemented.
3. Expected: only this department. No other department’s faculty/students.

### Approvals

1. Open **Approvals** (`/hod/approvals`).
2. Lists course plans professors submitted.
3. Open a plan. You can **Approve**, **Return / request changes**, or **Reject** (implemented actions). Draft plans cannot be approved until the professor submits.
4. Expected: professor gets a notification; plan status becomes approved, returned, or under review as chosen.

### Faculty

1. Open **Faculty** (`/hod/faculty`).
2. Professors in this department only.
3. Expected: `sandra@gmail.com` appears if that professor is in this department.

### Students

1. Open **Students** (`/hod/students`).
2. Department students only. Filters for year / section / class / UG-PG as the page provides.
3. Expected: `naveen@gmail.com` appears only if that student is in this department.

### Courses

See **section 14**.

### Analytics

1. Open **Analytics** (`/hod/analytics`).
2. Department analytics (students by year, workload-style views as implemented).
3. Expected: scoped to this department.

### Compliance

1. Open **Compliance** (`/hod/reports`).
2. Department compliance / NAAC-oriented report view from existing data.
3. Expected: page loads; no other department’s files.

### Notifications

1. Open **Notifications**.
2. Admin announcements to HODs, plus in-app notices.
3. Expected: Admin “All HODs” messages appear here.

### Settings

1. Open **Settings** (`/hod/settings`).
2. HOD account / department settings implemented on that page.
3. Expected: save works; it does not become college-wide Admin.

---

## 14. HOD → Course / Subject / Professor Workflow

This is the academic assignment chain the rest of the demo depends on.

```
HOD → Courses
  → Add course/subject (code, name, year, Odd/Even, Theory or Lab)
  → Assign professor + class
  → Class students are enrolled in that subject
  → Professor sees it on New Course Plan / teaching tools
  → Professor submits Course Plan
  → HOD → Approvals → approve or return
```

### On **Courses** (`/hod/subjects`)

1. Choose **Year** (1–4), **Semester** (Odd/Even), **Theory** or **Lab**.
2. **Add** a course: subject code, subject name, credits, contact hours, optional syllabus text.
3. **Assign professor**: subject, professor (same department), class (class year must match course year).
4. Expected: assignment is saved. Students in that class get the subject on **My Subjects**. The professor can generate a course plan for that assignment.

If the HOD account is not linked to a department, the page will say so. Fix that in **Users & Roles**.

---

## 15. Professor Testing

**Login**

- Sign in as **Professor**
- Email: `sandra@gmail.com`
- Password: `Password@123`

Expected: **Professor View** and `/professor/dashboard`.

Professor sidebar (actual labels):

**MAIN**

- Dashboard
- New Course Plan
- My Plans

**AI TOOLS**

- Lesson Planner
- Question Bank
- PPT Generator

**ACADEMIC**

- Assignments
- Notes
- Attendance
- Internal Marks
- Message Students
- Settings
- Notifications

AI tools (Course Plan, Lesson Planner, Question Bank, PPT, Assignment generate) need **Settings → AI Provider**: provider, model, and API key, then **Test / Connect**. Until connected, generate buttons ask you to open Settings.

### Dashboard

1. Open **Dashboard**.
2. Teaching snapshot for assigned work.
3. Expected: no other professor’s classes.

### New Course Plan

1. Open **New Course Plan** (`/professor/generate-plan`).
2. If nothing is assigned: message says HOD must assign courses under **HOD → Courses**.
3. If assigned: choose the subject/class, paste or use syllabus, generate (AI), then save.
4. Expected: a plan appears under **My Plans**.

### My Plans

1. Open **My Plans**.
2. Filter by status: draft, submitted, under review, approved, returned.
3. Open a plan. **Submit** sends it to the HOD.
4. Expected: HOD sees it on **Approvals**. After review, status updates here.

### Lesson Planner

1. Open **Lesson Planner**.
2. Build sessions from a course plan / unit (as the page provides).
3. Expected: sessions save for assigned courses only.

### Question Bank

1. Open **Question Bank**.
2. Generate or manage questions for an assigned subject/unit.
3. Expected: bank is stored for this professor.

### PPT Generator

1. Open **PPT Generator**.
2. Title, optional plan, context → **Generate PPT**.
3. Open / export PPTX when generation succeeds.
4. Expected: deck is listed. Timeouts can happen on very large “full course” prompts; a single unit is more reliable.

### Assignments

1. Open **Assignments**.
2. Create/generate an assignment for an assigned class. Only that class can submit.
3. Review submissions, extensions, grading as the page provides.
4. Expected: the matching student sees it; other classes do not.

### Notes

1. Open **Notes**.
2. Choose class/course, type **Notes** or **PPT**, title, file.
3. Notes: PDF, DOC, DOCX. PPT: PPT, PPTX, PDF. Max 20 MB.
4. **Upload & send to class**.
5. Expected: students in that class see it under **Study Materials**.

### Attendance

1. Open **Attendance**.
2. Take / view sessions for assigned classes (QR or list as implemented).
3. Expected: only assigned classes. Students see their own percentages.

### Internal Marks

1. Open **Internal Marks**.
2. Enter or view marks for assigned subjects/classes.
3. Expected: students see their own marks only.

### Message Students

1. Open **Message Students**.
2. Send a class message (optional attachment).
3. Expected: students in that class can receive it.

### Settings

1. Open **Settings**.
2. Connect **AI Provider** (OpenAI / Gemini / Claude). One provider is active.
3. Expected: after connect, generate forms work. Existing plans/PPTs are not wiped when you change provider.

### Notifications

1. Open **Notifications**.
2. Expected: Admin professor announcements, plan approval results, salary-paid notice if Admin marked salary Paid.

---

## 16. Professor → Assigned Subjects

The professor works only with **subject_assignments** created by the HOD.

Verify:

1. Log in as `sandra@gmail.com`.
2. **New Course Plan** lists the assigned subject and class (year / section).
3. Notes, Assignments, Attendance, and Marks class pickers show the same assignment.
4. A subject assigned to another professor must not appear.
5. Opening another professor’s plan or material by guessing an id should fail or show not found (institution + professor checks).

If the list is empty, go back to **HOD → Courses** and assign this professor.

---

## 17. Professor → Lesson Plan / Academic Workflow

Course Plan approval is the main HOD review workflow.

```
Professor → New Course Plan → generate/save (status draft)
  → My Plans → Submit
  → HOD → Approvals (cannot approve while still draft)
  → HOD Approve / Return / Reject
  → Professor sees approved or returned on My Plans
  → Professor is notified
```

Lesson Planner is the professor’s session breakdown after a plan exists. HOD Approvals in this application is for **course plans**, not a separate lesson-plan approval queue.

Also smoke-test:

1. Question Bank for the same unit/subject.
2. PPT from the same plan.
3. Assignment to the assigned class.
4. Notes upload to that class.
5. Student login confirms each item on the matching student screens.

---

## 18. Student Testing

**Login**

- Sign in as **Student**
- Email: `naveen@gmail.com`
- Password: `Password@123`

Expected: **Student View** and `/student/dashboard`.

Student sidebar (actual labels):

**MAIN**

- Dashboard
- My Subjects

**LEARNING**

- Assignments
- Study Materials
- Ask AI
- Calendar

**ACADEMIC**

- Attendance
- Internal Marks
- Academic History
- Fee History
- Notices

### Dashboard

Cards from **this student’s** data: attendance, pending assignments, **Days to exams**, fee status (if Admin created fees), upcoming items.

### My Subjects

Courses enrolled for this student (from HOD class assignment). Empty until that assignment exists.

### Assignments

Assignments the professor published to this student’s class. Submit if a deadline is open.

### Study Materials

Notes / PPT the professor uploaded to this class.

### Ask AI

Student AI helper (feature flag `ask_ai`). Uses the student Ask AI page, not the professor BYOK settings screen.

### Calendar

Academic events plus **Examination Schedule** from Admin Exam Timetable rows that match this student.

### Attendance

Own subject attendance only.

### Internal Marks

Own marks only.

### Academic History

Past academic records stored for this student.

### Fee History

See **section 22**. Dashboard **Fee Status** is the short version (section 21).

### Notices

Admin/professor notices targeted to this student.

---

## 19. Student → Exam Countdown

On **Student → Dashboard**, the exam card uses the **same Exam Timetable rows** as **Calendar**.

If a future matching exam exists, the card shows:

- **Today** if the exam is today
- **Tomorrow** / a day count for later dates
- Hint like `Database Management Systems · Oct 6`

If nothing upcoming matches this student: **—** and **No exam date set**.

The date comes from Admin **Exam Timetable**, not from a number typed on the dashboard.

---

## 20. Student → Exam Timetable

Students do not get an Admin timetable editor. They **read** matching exams on **Calendar** (and the dashboard countdown).

Example Admin record:

- Year: 1st Year
- Department: same as the student
- Academic level: UG (if the class is UG)
- Semester: same Odd/Even as the student
- Subject: Database Management Systems
- Date: October 6
- Start: 10:00 AM
- End: 1:00 PM
- Class/Section: empty (all sections) or this student’s section

**1st Year student** (`naveen@gmail.com` if that account is 1st Year): must see it.

**2nd Year student**: must not see it.

Also hidden when department, semester, UG/PG, or section does not match.

---

## 21. Student → Fee Status

On **Student → Dashboard**, panel **Fee Status**.

The student sees **only fee types Admin created** for that student.

Example Admin entry:

- Tuition Fee total ₹20,000
- Paid ₹10,000
- Pending ₹10,000

Student should see Tuition: those amounts and **Partially Paid**.

Do **not** show:

- Bus Fee → Unpaid (if no bus record)
- Hostel Fee → Unpaid (if no hostel record)

If Admin created nothing: empty / no fees recorded — not three unpaid types.

**View Fee History** goes to `/student/fees`.

Reminders appear if Admin used **Remind** and a pending amount exists.

---

## 22. Student → Fee History

**Where:** sidebar **Fee History** → `/student/fees`

Read-only. Session identity only (no `/student/fees/3` for another student).

Shown when data exists:

- Student name
- Register number
- Department
- Year
- Academic year
- Per fee: type, academic year, amount, paid, pending, payment date, status
- Payment history under each fee (if payments were recorded)
- Totals and overall status

**Student A must only see Student A.** Logging in as `naveen@gmail.com` must never show another student’s fees, even if you add `?student_id=` to the URL.

---

## 23. Student → Announcements / Notices

1. As Admin, send **Announcements** to **All Students**.
2. Log in as `naveen@gmail.com` → **Notices**.
3. Expected: that notice is listed.

Year-specific:

1. Admin sends **First Year Students**.
2. Only students whose academic year is 1 should get that audience.
3. A 2nd Year student should not receive a First Year audience notice.

Professor **Message Students** is a class message, also visible in the student notice/message areas the app uses for those messages.

---

## 24. Complete End-to-End Demo Flow

Use this sequence for a live demo.

### Step 1

Log in as **Admin**.

### Step 2

**Institution** → confirm college, academic year, semester → **Add department** if needed (Name + Code).

### Step 3

**Users & Roles** → **Add class** (department, UG/PG, year, name, section).

### Step 4

Create or confirm **HOD** (`csehod@test.com`, one HOD per department).

### Step 5

Create or confirm **Professor** (`sandra@gmail.com`, same department, qualification optional).

### Step 6

Create or confirm **Student** (`naveen@gmail.com`, department, class, academic year, semester).

### Step 7

Log out. Log in as **HOD** (`csehod@test.com` / `Password@123`).

### Step 8

**Courses** → add subject → **Assign professor** to the student’s class.

### Step 9

Log out. Log in as **Professor** (`sandra@gmail.com` / `Password@123`).

### Step 10

**Settings → AI Provider** → connect a key if you will generate AI content.

### Step 11

**New Course Plan** → generate/save for the assigned subject.

### Step 12

**My Plans** → **Submit**.

### Step 13

Optionally: Lesson Planner, Question Bank, PPT, Assignment, Notes, Attendance, Internal Marks for the same class.

### Step 14

Log out. Log in as **HOD**.

### Step 15

**Approvals** → open the plan → **Approve** (or return).

### Step 16

Log out. Log in as **Admin**.

### Step 17

**Exam Timetable** → add an exam for the student’s year, department, level, and semester.

### Step 18

**Fee Collection** → add Tuition (and only the extra types you want) → record a partial payment if you want Partially Paid.

### Step 19

**Announcements** → send to **All Students** (and optionally First Year Students).

### Step 20

**Salary & Payroll** → add/update this professor’s month → set **Paid** if you want the paid notification.

### Step 21

**Expense** → add one expense → open **Finance** and confirm totals moved.

### Step 22

Log out. Log in as **Student** (`naveen@gmail.com` / `Password@123`).

### Step 23

Verify:

- Dashboard (attendance, assignments, **Days to exams**, Fee Status)
- My Subjects (assigned course)
- Assignments
- Study Materials
- Calendar (exam row)
- Attendance
- Internal Marks
- Academic History
- Notices
- Fee History (own records only)

---

## 25. CRUD Testing Checklist

### Create

- [ ] Department (Institution → Add department)
- [ ] Class (Users & Roles → Add class)
- [ ] HOD user (one per department)
- [ ] Professor user
- [ ] Student user
- [ ] Course / subject (HOD → Courses)
- [ ] Professor–class assignment (HOD → Courses)
- [ ] Course plan (Professor → New Course Plan)
- [ ] Fee record (Fee Collection)
- [ ] Salary record (Salary & Payroll)
- [ ] Expense (Expense)
- [ ] Announcement (Announcements)
- [ ] Exam timetable row (Exam Timetable)
- [ ] Notes / PPT upload (Professor → Notes)
- [ ] Assignment (Professor → Assignments)

### Read

- [ ] Admin can view institution, users, fees, salary, finance, exams, announcements
- [ ] HOD can view own department faculty, students, courses, approvals
- [ ] Professor can view assigned plans, classes, and tools
- [ ] Student can view own courses, exams, fees, notices

### Update

- [ ] Edit user (Users & Roles)
- [ ] Edit institution profile
- [ ] Edit fee record / record payment
- [ ] Edit salary / change Paid–Pending–On Hold
- [ ] Edit exam timetable
- [ ] HOD approve / return a course plan
- [ ] Feature Flags toggle (optional)

### Delete

- [ ] Delete fee record
- [ ] Delete exam timetable row
- [ ] Delete study material (Professor → Notes)
- [ ] Delete assignment (Professor → Assignments, if used)
- [ ] Remove user access (Users & Roles toggle) — do not delete the protected College Admin

---

## 26. Role Permission Verification

| Feature | Admin | HOD | Professor | Student |
|---------|-------|-----|-----------|---------|
| Manage users, classes | Yes (Users & Roles) | No | No | No |
| Create departments | Yes (Institution) | No | No | No |
| Manage fees | Yes (Fee Collection) | No | No | View **own** Fee Status / Fee History |
| Manage salary | Yes (Salary & Payroll) | No | Receives paid notice only | No |
| Finance overview / Expense | Yes | No | No | No |
| Exam Timetable (create/edit/delete) | Yes | No | No | View matching exams on Calendar / dashboard |
| Announcements (college send) | Yes | View HOD inbox | View professor inbox | View Notices if targeted |
| Courses / assign professor | Catalog view on Institution | Yes (own department) | Uses assignment only | Enrolled subjects only |
| Course plan create/submit | No (not the professor workflow) | Review / approve / return | Create / submit assigned | No |
| Lesson / QB / PPT / Assignments / Notes | No | No | Assigned classes only | View / submit own |
| Attendance / Internal Marks | Overview on some Admin pages | Department views as implemented | Enter for assigned classes | View own |
| Ask AI | No | No | No (professor uses Settings AI + generators) | Yes (Ask AI) |

---

## 27. Important Testing Rules

### Rule 1

Never use one student’s login to confirm another student’s private data (fees, marks, assignments).

### Rule 2

Students must only see fee types Admin actually created for them.

### Rule 3

Students must only see Exam Timetable rows that match their year, department, semester, level, and section rules.

### Rule 4

Professors only use HOD-assigned subject/class combinations.

### Rule 5

HOD only sees their linked department.

### Rule 6

Admin has college-wide management for this institution (users, fees, salary, exams, announcements, finance). The primary College Admin account must not be broken or password-reset from the Users screen in ways the app blocks.

### Rule 7

Do not edit MySQL rows by hand during normal UI testing. Use the screens above.

### Rule 8

Do not create a second HOD in a department that already has an active HOD.

---

## 28. Final Testing Checklist

### Application

- [ ] MySQL running (XAMPP, port **3307** as configured)
- [ ] Apache running
- [ ] `http://localhost/professor/login` opens
- [ ] Database `proprofessor` connected
- [ ] Admin login works with the existing Admin password

### Admin

- [ ] Dashboard (Institution Overview) works
- [ ] Institution save + add department works
- [ ] Departments overview works
- [ ] Faculty list works
- [ ] Students list works
- [ ] Users & Roles create / class / one-HOD rule works
- [ ] Fee Collection works
- [ ] Salary & Payroll works
- [ ] Finance overview reflects live totals
- [ ] Expense create/list works
- [ ] Exam Timetable CRUD works
- [ ] Announcements send to the chosen audience
- [ ] Feature Flags / Marks Formulas / NAAC Builder / Analytics open

### HOD (`csehod@test.com`)

- [ ] Login works
- [ ] Dashboard works
- [ ] Faculty (department only) works
- [ ] Students (department only) works
- [ ] Courses add + assign professor works
- [ ] Approvals (submit required first) works
- [ ] Analytics works
- [ ] Compliance works
- [ ] Notifications work
- [ ] Settings open

### Professor (`sandra@gmail.com`)

- [ ] Login works
- [ ] Assigned subject/class appears
- [ ] AI Settings connect (if generating)
- [ ] New Course Plan + My Plans submit works
- [ ] Lesson Planner works
- [ ] Question Bank works
- [ ] PPT Generator works
- [ ] Assignments work
- [ ] Notes upload works
- [ ] Attendance works
- [ ] Internal Marks work
- [ ] Message Students works
- [ ] Notifications work

### Student (`naveen@gmail.com`)

- [ ] Login works
- [ ] Dashboard works
- [ ] Exam countdown matches timetable (or empty state)
- [ ] Calendar shows only matching exams
- [ ] My Subjects shows assigned courses
- [ ] Assignments work
- [ ] Study Materials work
- [ ] Ask AI opens
- [ ] Attendance works
- [ ] Internal Marks work
- [ ] Academic History opens
- [ ] Notices show targeted announcements
- [ ] Fee Status shows only created fee types
- [ ] Fee History is own records only

---

## 29. Demo Completion

**ProProfessor AI Manual Testing Complete**

The application has been tested through the Admin, HOD, Professor, and Student roles, and the major workflows have been verified according to the implemented functionality.
