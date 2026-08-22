# ProProfessor AI — PHP MVC + MySQL

India-focused AI academic OS with a proper **MVC** front controller.

## MVC layout

```
professor/
├── index.php                 # Front controller
├── .htaccess                 # Pretty URLs → index.php
├── routes/web.php            # Route map
├── app/
│   ├── bootstrap.php
│   ├── Core/                 # App, Router, Controller, Model, View, Autoloader
│   ├── Controllers/
│   │   ├── Auth/
│   │   ├── Admin/            # Full MVC (dashboard, users, features, finance…)
│   │   ├── Professor/
│   │   ├── Student/
│   │   ├── Hod/
│   │   ├── Api/
│   │   ├── NotificationController.php
│   │   └── LegacyController.php   # Bridges remaining modules into MVC routing
│   ├── Models/               # User, CoursePlan, Expense, …
│   ├── Services/             # NavService, …
│   └── Views/                # layouts + role views
├── config/
├── database/
├── assets/
└── professor|student|hod/    # Module scripts served via LegacyController
```

**Pattern:** Route → Controller → Model/Service → View

## Admin interface (yes)

Full admin portal (MVC):

| URL | Purpose |
|-----|---------|
| `/professor/admin/dashboard` | Stats + module hub |
| `/professor/admin/institution` | College setup |
| `/professor/admin/users` | Roles & users |
| `/professor/admin/features` | Expandable feature flags |
| `/professor/admin/formulas` | Internal marks formulas |
| `/professor/admin/finance` | Expenses |
| `/professor/admin/naac` | NAAC snapshot |
| `/professor/admin/analytics` | Institution KPIs |
| `/professor/admin/billing` | Subscription seats |

Login: `admin@proprofessor.local` / `Password@123`

## Quick start (local XAMPP)

1. Apache + MySQL (XAMPP)
2. `http://localhost/professor/install.php` (if DB not seeded)
3. `http://localhost/professor/login`
4. Optional Gemini key in `config/config.php`

Enable `mod_rewrite` in Apache (XAMPP default usually on).

## Deploy to hosting (e.g. zayith.com/demo/professor)

1. Upload the full project folder to `public_html/demo/professor` (or your host’s web root + `/demo/professor`).
2. In cPanel → MySQL, create a database + user, and grant all privileges.
3. On the server, copy `config/config.local.php.example` → `config/config.local.php` and set:
   - `db.name`, `db.user`, `db.pass` (and `db.host` if not `localhost`)
4. Visit `https://zayith.com/demo/professor/install.php` once to create tables + demo users.
5. Open `https://zayith.com/demo/professor/login`
6. Delete or rename `install.php` after a successful install.

`base_url` is **auto** (detects `/demo/professor`). Pretty URLs need Apache `mod_rewrite` and `AllowOverride All` for `.htaccess`.

If you only see a 404 for the whole folder, the files are not in the path the domain expects — confirm the upload path in File Manager.

## Roles

- Professor — `/professor/dashboard` (under your base path)
- Student — `/student/dashboard`
- HOD — `/hod/dashboard`
- Admin — `/admin/dashboard`



You are a SENIOR FULL-STACK ARCHITECT, SOFTWARE ENGINEER, DATABASE ARCHITECT, SECURITY ENGINEER, and AI APPLICATION DEVELOPER.

Your task is to DESIGN AND BUILD the complete production-ready MVP of:

============================================================
                    PROPROFESSOR AI
             MULTI-TENANT EDUCATION SaaS
============================================================

IMPORTANT:

Do NOT treat this as a demo.

Do NOT create only a frontend mockup.

Do NOT create only APIs.

Do NOT create placeholder pages.

Do NOT create fake data as the actual implementation.

Build a complete working full-stack application using:

React.js
JavaScript
Normal CSS
Node.js
Express.js
MySQL
JWT
bcrypt/bcryptjs
Multer
Axios
Recharts


professors/
│
├── server/
│ 
└── client/
    ├── src/
   
    
SERVER = BACKEND
CLIENT = FRONTEND

The application must be a TRUE MULTI-TENANT SaaS PLATFORM.

============================================================
1. CORE BUSINESS IDEA
============================================================

ProProfessor AI is ONE centralized SaaS platform that can be used by:

College A
College B
College C
College D
...
College N

There is NO fixed number of colleges.

The system must support ANY NUMBER OF INSTITUTIONS.

For example:

Institution 1
Institution 2
Institution 3
Institution 10
Institution 100
Institution 1000
Institution 10000

The application must continue working without changing source code.

NEVER hardcode a college.

NEVER create special code for XYZ College.

NEVER create special code for ABC College.

XYZ College and ABC College are ONLY seed/test tenants.

They are NOT special tenants.

The architecture must be:

                    PROPROFESSOR AI
                           |
          +----------------+----------------+
          |                |                |
    Institution A    Institution B    Institution C
      Tenant 1          Tenant 2          Tenant 3
          |                |                |
    College Admin    College Admin    College Admin
          |                |                |
     Departments      Departments      Departments
          |                |                |
      HOD/Professors  HOD/Professors  HOD/Professors
          |                |                |
       Students          Students          Students
          |                |                |
        Courses          Courses          Courses
          |                |                |
     Academic Data    Academic Data    Academic Data
          |                |                |
       Student AI       Student AI       Student AI

This hierarchy continues dynamically for:

Institution D
Institution E
Institution F
...
Institution N

============================================================
2. MULTI-TENANCY IS THE MOST IMPORTANT REQUIREMENT
============================================================

Every college/institution is a tenant.

Every tenant has a unique:

institution_id

The database generates institution IDs.

Never hardcode institution IDs.

Never assume a maximum institution count.

Never write:

if (institutionId === 101)

Never write:

if (institution === "XYZ")

Never write:

if (institution === "ABC")

Never create:

xyzUsers
abcUsers

Never create:

xyzCourses
abcCourses

Never create:

XYZ-specific routes.

Never create:

ABC-specific routes.

Never create:

XYZ-specific controllers.

Never create:

ABC-specific controllers.

Never create:

separate backend servers per institution.

Never create:

separate React applications per institution.

Never create:

separate databases per institution.

Use ONE application.

Use ONE backend.

Use ONE React frontend.

Use ONE MySQL database.

Use database-driven tenant isolation.

============================================================
3. TENANT ISOLATION
============================================================

Every institution's data must be isolated.

XYZ users can access only XYZ data.

ABC users can access only ABC data.

PQR users can access only PQR data.

A user must NEVER access another institution's:

users
departments
HODs
professors
students
courses
course plans
course materials
attendance
marks
assignments
submissions
announcements
notifications
AI conversations
AI generated content
uploaded files
analytics
audit logs

The backend is the final security authority.

The frontend is NEVER the security layer.

If a malicious user changes:

institution_id

inside browser DevTools, URL, query parameter, request body, or API request:

THE BACKEND MUST STILL BLOCK ACCESS.

============================================================
4. AUTHENTICATED TENANT CONTEXT
============================================================

After login, JWT must contain:

user_id
institution_id
role
department_id

Example:

{
    "user_id": 25,
    "institution_id": 101,
    "role": "PROFESSOR",
    "department_id": 10
}

Backend should populate:

req.user = {
    id,
    institution_id,
    role,
    department_id
}

For every institution-sensitive operation:

ALWAYS derive tenant context from:

req.user.institution_id

NEVER trust:

req.body.institution_id

as the authorization source.

NEVER trust:

req.query.institution_id

NEVER trust:

req.params.institution_id

The authenticated user's institution is the source of truth.

============================================================
5. TECHNOLOGY STACK
============================================================

FRONTEND:

React.js
JavaScript
React Router
Axios
Recharts
Normal CSS

BACKEND:

Node.js
Express.js
JavaScript
REST APIs
JWT
bcrypt/bcryptjs
Multer

DATABASE:

MySQL
mysql2

DO NOT USE:

TypeScript
Tailwind CSS
MongoDB
PostgreSQL
Firebase
Supabase
Prisma
Next.js
shadcn/ui

Use:

React + Normal CSS + Express + MySQL.

============================================================
6. DATABASE CONFIGURATION
============================================================

Use environment variables.

Local development:

DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=root123
DB_NAME=proprofessor_ai

Create:

backend/.env.example

Example:

PORT=5000

DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=root123
DB_NAME=proprofessor_ai

JWT_SECRET=change_this_secret

AI_API_KEY=
AI_MODEL=

FRONTEND_URL=http://localhost:5173

Never hardcode secrets inside JavaScript files.

Never expose AI API keys to React.

============================================================
7. PROJECT STRUCTURE
============================================================

Create:

proprofessor/

    frontend/

    backend/

Do not destroy unrelated projects.

Before modifying the workspace:

1. Inspect the existing workspace.
2. Identify existing applications.
3. Do not delete unrelated projects.
4. Do not overwrite unrelated files.
5. Create ProProfessor separately.

============================================================
8. BACKEND ARCHITECTURE
============================================================

Backend entry point MUST be:

backend/server.js

DO NOT create:

app.js

The backend must use a clean MVC-style architecture.

Required structure:

backend/

    config/
        db.js

    controllers/
        authController.js
        platformController.js
        adminController.js
        hodController.js
        professorController.js
        studentController.js
        aiController.js
        studentAiController.js
        attendanceController.js
        marksController.js
        assignmentController.js
        materialController.js
        announcementController.js
        notificationController.js
        analyticsController.js

    middleware/
        authMiddleware.js
        roleMiddleware.js
        tenantMiddleware.js
        uploadMiddleware.js
        errorMiddleware.js
        validationMiddleware.js

    models/
        userModel.js
        institutionModel.js
        departmentModel.js
        courseModel.js
        coursePlanModel.js
        coursePlanVersionModel.js
        materialModel.js
        attendanceModel.js
        marksModel.js
        assignmentModel.js
        announcementModel.js
        notificationModel.js
        aiModel.js
        auditLogModel.js

    routes/
        authRoutes.js
        platformRoutes.js
        adminRoutes.js
        hodRoutes.js
        professorRoutes.js
        studentRoutes.js
        aiRoutes.js
        studentAiRoutes.js
        attendanceRoutes.js
        marksRoutes.js
        assignmentRoutes.js
        materialRoutes.js
        announcementRoutes.js
        notificationRoutes.js
        analyticsRoutes.js

    services/
        aiService.js
        embeddingService.js
        vectorSearchService.js
        fileService.js
        notificationService.js
        auditService.js

    utils/
        jwt.js
        password.js
        validators.js
        response.js
        constants.js

    uploads/

    database/
        schema.sql
        seed.sql

    server.js

Backend responsibilities:

ROUTES:
Only define API endpoints and middleware.

CONTROLLERS:
Handle request/response and business workflow orchestration.

MODELS:
Handle MySQL queries/database operations.

MIDDLEWARE:
Authentication
Authorization
Tenant isolation
Validation
File upload
Error handling

SERVICES:
Reusable business integrations such as AI, file processing, notifications, audit logging.

DO NOT put all business logic inside server.js.

DO NOT put SQL directly throughout routes.

DO NOT create a giant controller containing every feature.

Keep responsibilities separated.

============================================================
9. FRONTEND ARCHITECTURE
============================================================

Create:

frontend/src/

    components/
    pages/
    layouts/
    routes/
    services/
    context/
    hooks/
    utils/
    styles/

Use:

AuthContext

ProtectedRoute

RoleProtectedRoute

Axios API client.

Create role layouts:

PlatformAdminLayout
CollegeAdminLayout
HodLayout
ProfessorLayout
StudentLayout

============================================================
10. USER ROLES
============================================================

Implement exactly these roles:

PLATFORM_ADMIN
COLLEGE_ADMIN
HOD
PROFESSOR
STUDENT

============================================================
11. PLATFORM ADMIN
============================================================

Platform Admin manages the entire SaaS platform.

Platform Admin can:

Create institutions
View institutions
Update institutions
Activate institutions
Deactivate institutions
Create initial College Admin
View platform analytics

APIs:

GET /api/platform/institutions

POST /api/platform/institutions

GET /api/platform/institutions/:id

PUT /api/platform/institutions/:id

PATCH /api/platform/institutions/:id/status

POST /api/platform/institutions/:id/admin

Platform Admin can create a new college dynamically.

For example:

PQR College

without modifying source code.

============================================================
12. COLLEGE ADMIN
============================================================

College Admin belongs to exactly ONE institution.

College Admin can manage ONLY their own institution.

College Admin can:

Create departments
Update departments
Delete departments
Create HODs
Create Professors
Create Students
Update users
Deactivate users
Manage courses
Create announcements
View institution analytics

When College Admin creates a user:

institution_id MUST automatically be:

req.user.institution_id

Never accept tenant ownership from frontend.

============================================================
13. DEPARTMENTS
============================================================

Departments belong to an institution.

Example:

XYZ:

CSE
IT
MECH
EEE
ECE

ABC:

CSE
IT
MECH
EEE
ECE

This is completely valid.

Department codes need only be unique within their institution.

Use:

UNIQUE(institution_id, code)

============================================================
14. HOD
============================================================

HOD belongs to:

one institution
one department

HOD can:

View department professors
View department students
View department courses
View submitted course plans
Review course plans
Approve course plans
Reject course plans
Request revisions
Add review comments
View department analytics

HOD cannot access another institution.

HOD cannot access another department unless explicitly authorized.

============================================================
15. PROFESSOR
============================================================

Professor belongs to:

one institution
one department

Professor can:

View assigned courses
Create course plans
Edit course plans
Generate course plans using AI
Review course plans using AI
Improve course plans using AI
Map Bloom's taxonomy
Generate lesson plans
Generate question banks
Generate PPT content
Generate assignments
Upload course materials
Submit course plans to HOD
Manage attendance
Manage marks

============================================================
16. STUDENT
============================================================

Student belongs to:

one institution
one department

Student can:

View enrolled courses
View approved course materials
View PDFs
View PPTs
View assignments
Submit assignments
View own attendance
View own marks
View announcements
Use Student AI

Student MUST NEVER access another student's private data.

============================================================
17. DATABASE
============================================================

Create database:

proprofessor_ai

Create proper foreign keys.

Use indexes.

Use timestamps.

Use transactions for multi-step operations where appropriate.

Minimum tables:

institutions
users
departments
professor_profiles
hod_profiles
student_profiles
courses
course_assignments
course_enrollments
course_plans
course_plan_versions
course_units
learning_outcomes
bloom_mappings
course_plan_reviews
lesson_plans
question_banks
questions
assignments
assignment_submissions
course_materials
attendance
attendance_records
internal_mark_components
internal_marks
announcements
notifications
ai_generations
ai_conversations
ai_messages
audit_logs

Every institution-specific table must contain:

institution_id

where applicable.

============================================================
18. INSTITUTIONS TABLE
============================================================

Fields:

id
name
code
email
phone
address
status
created_at
updated_at

id:

AUTO_INCREMENT PRIMARY KEY

code:

UNIQUE

status:

ACTIVE
INACTIVE

============================================================
19. USERS TABLE
============================================================

Fields:

id
institution_id
name
email
password_hash
role
department_id
status
created_at
updated_at

PLATFORM_ADMIN:

institution_id can be NULL.

COLLEGE_ADMIN:

institution_id required.

HOD:

institution_id required
department_id required.

PROFESSOR:

institution_id required
department_id required.

STUDENT:

institution_id required
department_id required.

Passwords:

NEVER store plain passwords.

Store:

password_hash

Never return password_hash in API responses.

Never log passwords.

============================================================
20. AUTHENTICATION
============================================================

Implement:

POST /api/auth/login

GET /api/auth/profile

PUT /api/auth/profile

PUT /api/auth/change-password

Use:

bcrypt/bcryptjs

JWT.

Create:

authenticate

authorize

requirePlatformAdmin

requireCollegeAdmin

requireHod

requireProfessor

requireStudent

Middleware must be reusable.

============================================================
21. TENANT MIDDLEWARE
============================================================

Create:

tenantMiddleware.js

Its purpose is to ensure that tenant-sensitive requests use:

req.user.institution_id

Never trust institution_id from request body.

All tenant-aware controllers must use:

const institutionId = req.user.institution_id;

Database queries must include tenant filtering.

Example:

SELECT *
FROM courses
WHERE id = ?
AND institution_id = ?

For lists:

SELECT *
FROM courses
WHERE institution_id = ?

This rule must be followed throughout the entire backend.

============================================================
22. OWNERSHIP SECURITY
============================================================

Tenant isolation alone is NOT sufficient.

Implement ownership checks.

Examples:

Professor A cannot edit Professor B's course plan.

Student A cannot view Student B's marks.

Student A cannot view Student B's attendance.

Student A cannot submit another student's assignment.

HOD CSE cannot review another department's course plan unless authorized.

College Admin cannot manage another institution.

============================================================
23. COURSE PLAN WORKFLOW
============================================================

Statuses:

DRAFT
SUBMITTED
UNDER_REVIEW
APPROVED
REJECTED
REVISION_REQUIRED

Workflow:

Professor
    |
    v
DRAFT
    |
    v
SUBMITTED
    |
    v
HOD REVIEW
    |
    +------ APPROVED
    |
    +------ REJECTED
    |
    +------ REVISION_REQUIRED
                    |
                    v
                PROFESSOR
                    |
                    v
                 RESUBMIT

Never overwrite previous course plan versions.

Create:

course_plan_versions

Version:

1
2
3
...

When AI improves a course plan:

CREATE A NEW VERSION.

Never destroy the previous version.

============================================================
24. BLOOM'S TAXONOMY
============================================================

Support:

K1 Remember
K2 Understand
K3 Apply
K4 Analyze
K5 Evaluate
K6 Create

Map learning outcomes to Bloom levels.

Provide Bloom analytics.

============================================================
25. AI FEATURES
============================================================

AI API keys MUST stay in backend.

Never expose AI API keys in React.

Create:

services/aiService.js

Environment:

AI_API_KEY=
AI_MODEL=

Implement:

Course Plan Generator
Course Plan Reviewer
Course Plan Improver
Lesson Plan Generator
Question Bank Generator
PPT Generator
Assignment Generator
Student AI

============================================================
26. AI COURSE PLAN GENERATOR
============================================================

Endpoint:

POST /api/ai/course-plan/generate

Input:

course
syllabus
semester
academic_year
credits
hours

Return structured JSON.

Validate AI response before saving.

============================================================
27. AI COURSE PLAN REVIEWER
============================================================

Endpoint:

POST /api/ai/course-plan/review

Review:

Learning outcomes
OBE alignment
Bloom distribution
Industry relevance
Assessment alignment
Contact hours
Teaching methods

Return:

score
strengths
weaknesses
recommendations

============================================================
28. AI COURSE PLAN IMPROVER
============================================================

Endpoint:

POST /api/ai/course-plan/improve

Professor can request:

"Increase K4 outcomes in Unit 3."

Create an improved version.

Save it as a new course_plan_version.

============================================================
29. AI LESSON PLAN
============================================================

Endpoint:

POST /api/ai/lesson-plan/generate

Generate:

topic
objectives
duration
teaching_method
activities
assessment
engagement

============================================================
30. AI QUESTION BANK
============================================================

Endpoint:

POST /api/ai/questions/generate

Support:

MCQ
Short Answer
Essay

Fields:

course
unit
bloom_level
difficulty
marks

============================================================
31. AI PPT
============================================================

Endpoint:

POST /api/ai/ppt/generate

Generate structured slide data.

Frontend must display a PPT preview.

Keep architecture ready for future real .pptx generation.

============================================================
32. AI ASSIGNMENT
============================================================

Endpoint:

POST /api/ai/assignment/generate

Generate:

title
description
instructions
marks
deadline
rubric

============================================================
33. FILE UPLOADS
============================================================

Professors can upload:

PDF
PPT
PPTX
DOC
DOCX
images

Use Multer.

Storage must be tenant-aware.

Never:

uploads/XYZ/

Never:

uploads/ABC/

Use:

uploads/
    institutions/
        <institution_id>/
            materials/
            assignments/
            documents/

Example:

uploads/institutions/101/materials/

uploads/institutions/102/materials/

Every file access must verify:

Authentication
Institution
Resource ownership/access

Unauthorized file request:

403 or 404

Never expose another institution's file.

============================================================
34. ATTENDANCE
============================================================

Professor selects:

Course
Date

Then records:

Present
Absent

Store:

institution_id
course_id
student_id
date
status
marked_by

Calculate attendance percentage.

Professor sees attendance only for assigned courses.

Students see only their own attendance.

============================================================
35. MARKS
============================================================

Support configurable components:

CIA 1
CIA 2
Assignment
Attendance
Other

Professor manages marks.

Students can view their own marks.

Students cannot modify marks.

============================================================
36. ASSIGNMENTS
============================================================

Professor:

Create
Publish
Set deadline
Set marks
Set rubric

Student:

View
Submit
View status
View marks
View feedback

Professor:

Review
Grade
Give feedback

============================================================
37. STUDENT AI
============================================================

Endpoint:

POST /api/student-ai/chat

Student AI MUST be tenant-safe.

Retrieval must be restricted by:

institution_id
student_id
enrolled course IDs

NEVER retrieve documents from another institution.

Create:

services/embeddingService.js

services/vectorSearchService.js

services/aiService.js

Document metadata must contain:

institution_id
course_id
uploaded_by

Any future vector search must filter by:

institution_id

If vector database is not implemented in MVP:

create a clean abstraction/interface.

Do NOT add another database just for the MVP.

============================================================
38. NOTIFICATIONS
============================================================

Create notifications for:

Course plan submitted
Course plan approved
Course plan rejected
Revision requested
Assignment posted
Assignment graded
Attendance warning
Announcements

Fields:

user_id
institution_id
title
message
type
is_read
created_at

Tenant isolation required.

============================================================
39. ANNOUNCEMENTS
============================================================

College Admin:

Institution-wide announcements.

HOD:

Department announcements where permitted.

Announcements must be tenant isolated.

============================================================
40. ANALYTICS
============================================================

PLATFORM ADMIN:

Total institutions
Active institutions
Inactive institutions
Total users
Total colleges

COLLEGE ADMIN:

Total departments
Total HODs
Total professors
Total students
Total courses
Total course plans
Pending plans
Approved plans
Rejected plans
Materials
Attendance overview

HOD:

Department professors
Department students
Pending approvals
Course plan status
Bloom distribution
Attendance

PROFESSOR:

Assigned courses
Course plans
Approval status
Attendance
Assignments
Marks

STUDENT:

Courses
Attendance
Marks
Assignments
Materials

Use Recharts where charts add value.

============================================================
41. AUDIT LOGS
============================================================

Log:

Login
User creation
User update
Course creation
Course plan creation
Course plan submission
Course plan approval
Course plan rejection
Material upload
Attendance update
Marks update
Assignment submission

Store:

user_id
institution_id
action
entity_type
entity_id
metadata
created_at

Never log passwords.

============================================================
42. API STRUCTURE
============================================================

AUTH:

POST /api/auth/login
GET /api/auth/profile
PUT /api/auth/profile
PUT /api/auth/change-password

PLATFORM:

GET /api/platform/institutions
POST /api/platform/institutions
GET /api/platform/institutions/:id
PUT /api/platform/institutions/:id
PATCH /api/platform/institutions/:id/status
POST /api/platform/institutions/:id/admin

ADMIN:

GET /api/admin/dashboard

GET /api/admin/departments
POST /api/admin/departments
PUT /api/admin/departments/:id
DELETE /api/admin/departments/:id

GET /api/admin/users
POST /api/admin/users
GET /api/admin/users/:id
PUT /api/admin/users/:id
PATCH /api/admin/users/:id/status

HOD:

GET /api/hod/dashboard
GET /api/hod/course-plans
GET /api/hod/course-plans/:id
PUT /api/hod/course-plans/:id/approve
PUT /api/hod/course-plans/:id/reject
PUT /api/hod/course-plans/:id/revision

PROFESSOR:

GET /api/professor/dashboard
GET /api/professor/courses
GET /api/professor/course-plans
POST /api/professor/course-plans
PUT /api/professor/course-plans/:id
POST /api/professor/course-plans/:id/submit

POST /api/professor/materials/upload

POST /api/professor/attendance
GET /api/professor/attendance

POST /api/professor/marks
GET /api/professor/marks

STUDENT:

GET /api/student/dashboard
GET /api/student/courses
GET /api/student/materials
GET /api/student/attendance
GET /api/student/marks
GET /api/student/assignments
POST /api/student/assignments/:id/submit

AI:

POST /api/ai/course-plan/generate
POST /api/ai/course-plan/review
POST /api/ai/course-plan/improve
POST /api/ai/lesson-plan/generate
POST /api/ai/questions/generate
POST /api/ai/ppt/generate
POST /api/ai/assignment/generate

STUDENT AI:

POST /api/student-ai/chat

============================================================
43. API RESPONSE FORMAT
============================================================

Success:

{
    "success": true,
    "data": {}
}

Error:

{
    "success": false,
    "message": "Something went wrong"
}

Use correct HTTP status codes.

400
401
403
404
409
422
500

Do not expose sensitive SQL errors.

============================================================
44. VALIDATION
============================================================

Validate backend inputs.

Validate:

email
password
name
department
course
marks
attendance
dates
files
course plans
assignment submissions

Never rely only on frontend validation.

Backend validation is mandatory.

============================================================
45. ERROR HANDLING
============================================================

Implement centralized Express error handling.

Create:

middleware/errorMiddleware.js

Handle:

400
401
403
404
409
422
500

Return safe messages.

Do not expose:

SQL stack traces
passwords
JWT secrets
AI keys
internal filesystem paths

============================================================
46. FRONTEND AUTH
============================================================

AuthContext must expose:

user
token
role
institution_id
department_id
isAuthenticated
login()
logout()

After login:

PLATFORM_ADMIN
→ Platform Dashboard

COLLEGE_ADMIN
→ College Admin Dashboard

HOD
→ HOD Dashboard

PROFESSOR
→ Professor Dashboard

STUDENT
→ Student Dashboard

Create:

ProtectedRoute

RoleProtectedRoute

============================================================
47. FRONTEND UI
============================================================

Build a professional SaaS UI.

Use normal CSS.

Required:

Sidebar
Navbar
Cards
Tables
Forms
Modals
Charts
Status badges
Loading states
Empty states
Error states
Success messages

Responsive:

Desktop
Tablet
Mobile

Do not make the UI look like a basic tutorial project.

Keep consistent spacing, typography, forms, tables and navigation.

============================================================
48. PLATFORM ADMIN PAGES
============================================================

Create pages for:

Login
Platform Dashboard
Institutions
Create Institution
Edit Institution
Institution Details
Create College Admin
Platform Analytics

============================================================
49. COLLEGE ADMIN PAGES
============================================================

Create:

Dashboard
Departments
Create Department
Edit Department
Users
Create User
Edit User
HOD Management
Professor Management
Student Management
Courses
Announcements
Analytics
Profile
Settings

============================================================
50. HOD PAGES
============================================================

Create:

Dashboard
Professors
Students
Courses
Course Plan Review
Course Plan Details
Approve
Reject
Request Revision
Department Analytics
Announcements
Profile

============================================================
51. PROFESSOR PAGES
============================================================

Create:

Dashboard
My Courses
Course Plans
Create Course Plan
Edit Course Plan
AI Course Plan Generator
AI Course Plan Review
AI Course Plan Improver
Course Plan Versions
Lesson Plan Generator
Question Bank Generator
PPT Generator
Assignment Generator
Course Materials
Upload Material
Attendance
Marks
Assignments
Profile

============================================================
52. STUDENT PAGES
============================================================

Create:

Dashboard
My Courses
Course Materials
Assignments
Submit Assignment
Attendance
Marks
Announcements
Notifications
Student AI
Profile

============================================================
53. DATABASE SECURITY
============================================================

Every tenant-sensitive query must filter by:

institution_id

Examples:

SELECT *
FROM users
WHERE institution_id = ?

SELECT *
FROM courses
WHERE institution_id = ?

SELECT *
FROM course_plans
WHERE institution_id = ?

SELECT *
FROM attendance_records
WHERE institution_id = ?

SELECT *
FROM internal_marks
WHERE institution_id = ?

Never query by ID alone when tenant isolation is required.

BAD:

SELECT * FROM courses WHERE id = ?

GOOD:

SELECT *
FROM courses
WHERE id = ?
AND institution_id = ?

Also verify ownership.

============================================================
54. DATABASE INDEXES
============================================================

Create indexes on:

institution_id

institution_id + department_id

institution_id + role

institution_id + course_id

institution_id + student_id

institution_id + professor_id

Use foreign keys.

Use appropriate ON DELETE behavior.

Avoid orphan records.

============================================================
55. SEED DATA
============================================================

Create development seed data.

Seed at least:

XYZ College
ABC College

These are ONLY test tenants.

Create:

Platform Admin

XYZ College Admin
XYZ CSE HOD
XYZ Professors
XYZ Students

ABC College Admin
ABC CSE HOD
ABC Professors
ABC Students

Create sample:

Departments
Courses
Course plans
Course plan versions
Materials metadata
Attendance
Marks
Assignments
Announcements

Use safe development passwords.

Clearly document them in README.

Do NOT use these seed institutions as special logic.

============================================================
56. REQUIRED MULTI-TENANT SECURITY TESTS
============================================================

After implementation, verify:

1. XYZ Admin can see XYZ users.

2. XYZ Admin cannot see ABC users.

3. ABC Admin can see ABC users.

4. ABC Admin cannot see XYZ users.

5. XYZ Professor cannot access ABC courses.

6. ABC Professor cannot access XYZ courses.

7. XYZ Student cannot access ABC marks.

8. ABC Student cannot access XYZ marks.

9. XYZ user cannot download ABC files.

10. ABC user cannot download XYZ files.

11. XYZ HOD cannot approve ABC course plan.

12. ABC HOD cannot approve XYZ course plan.

13. Professor cannot modify another professor's course plan.

14. Student cannot modify marks.

15. Changing institution IDs in URLs cannot bypass security.

16. Sending another institution_id in request body cannot bypass security.

17. Sending another institution_id in query parameters cannot bypass security.

18. Changing resource IDs cannot bypass ownership checks.

19. A disabled user cannot access protected APIs.

20. A disabled institution's users cannot access tenant data.

============================================================
57. THIRD TENANT TEST
============================================================

After creating XYZ and ABC seed data:

Use the normal Platform Admin functionality to create:

PQR College

DO NOT modify source code.

DO NOT add special conditionals.

DO NOT add special routes.

DO NOT add special tables.

Then create through the application:

PQR College Admin

PQR departments

PQR HOD

PQR Professors

PQR Students

Verify:

PQR works exactly like XYZ and ABC.

PQR cannot access XYZ.

PQR cannot access ABC.

XYZ cannot access PQR.

ABC cannot access PQR.

This proves true dynamic multi-tenancy.

============================================================
58. FILE SECURITY
============================================================

All file paths must be generated dynamically.

Use:

uploads/institutions/<institution_id>/

Never use institution names as hardcoded directories.

Do not trust a client-supplied filesystem path.

Prevent path traversal.

Validate file types.

Validate file sizes.

Never allow a user to directly access arbitrary filesystem paths.

Create secure download/view endpoints.

============================================================
59. AI SECURITY
============================================================

AI is tenant-sensitive.

Every AI generation must be associated with:

institution_id
user_id
course_id where applicable

Student AI retrieval must filter by:

institution_id
student_id
enrolled course IDs

Professor AI must operate only on:

institution
department
assigned courses
authorized resources

Never mix AI context between institutions.

============================================================
60. PERFORMANCE
============================================================

Design the database for many institutions.

Do not load all institutions' data into memory.

Always use tenant-filtered SQL queries.

Use pagination for:

Users
Courses
Course plans
Materials
Assignments
Notifications
Audit logs

Avoid unnecessary database queries.

Use indexes.

============================================================
61. CODE QUALITY
============================================================

Write clean production-style code.

Use meaningful variable names.

Avoid duplicated code.

Use reusable middleware.

Use reusable services.

Use reusable model functions.

Keep controllers readable.

Keep routes thin.

Keep SQL inside models/database layer.

Do not create giant files unnecessarily.

Add comments only where they clarify important logic.

============================================================
62. README
============================================================

Create a complete README explaining:

Project overview
Architecture
Technology stack
Folder structure
Environment variables
MySQL setup
Database creation
Schema setup
Seed setup
Backend setup
Frontend setup
How to run
API overview
Roles
Tenant architecture
Security model
Test credentials
Development workflow

============================================================
63. DEVELOPMENT COMMANDS
============================================================

Frontend:

npm install
npm run dev

Backend:

npm install
npm run dev

Use nodemon for backend development if appropriate.

Do not use app.js.

Backend starts from:

server.js

============================================================
64. HEALTH CHECK
============================================================

Create:

GET /api/health

Response:

{
    "success": true,
    "message": "ProProfessor API is running"
}

============================================================
65. IMPLEMENTATION ORDER
============================================================

Although this is ONE complete implementation, build it internally in this order:

STEP 1:
Inspect workspace.

STEP 2:
Create project structure.

STEP 3:
Initialize React.

STEP 4:
Initialize Express.

STEP 5:
Configure environment.

STEP 6:
Configure MySQL.

STEP 7:
Create database schema.

STEP 8:
Create models.

STEP 9:
Create authentication.

STEP 10:
Create JWT middleware.

STEP 11:
Create role middleware.

STEP 12:
Create tenant isolation middleware.

STEP 13:
Create Platform Admin.

STEP 14:
Create College Admin.

STEP 15:
Create Departments.

STEP 16:
Create HOD.

STEP 17:
Create Professor.

STEP 18:
Create Student.

STEP 19:
Create course management.

STEP 20:
Create course plan workflow.

STEP 21:
Create versioning.

STEP 22:
Create materials.

STEP 23:
Create attendance.

STEP 24:
Create marks.

STEP 25:
Create assignments.

STEP 26:
Create announcements.

STEP 27:
Create notifications.

STEP 28:
Create AI services.

STEP 29:
Create Student AI abstraction.

STEP 30:
Create analytics.

STEP 31:
Create audit logs.

STEP 32:
Create frontend authentication.

STEP 33:
Create role-based layouts.

STEP 34:
Create all dashboards.

STEP 35:
Create all management pages.

STEP 36:
Create responsive CSS.

STEP 37:
Connect frontend to backend APIs.

STEP 38:
Seed multiple institutions.

STEP 39:
Run security tests.

STEP 40:
Run application tests.

STEP 41:
Fix all errors.

STEP 42:
Verify complete application.

DO NOT stop after Phase 1.

DO NOT stop after Phase 2.

Build the COMPLETE MVP.

============================================================
66. IMPORTANT CURSOR BEHAVIOR
============================================================

You have permission to create and modify all files required for the ProProfessor application.

Before coding:

INSPECT the workspace.

Do not blindly overwrite existing projects.

Then create the ProProfessor project separately.

Do not ask unnecessary questions when requirements are already specified.

If a technical implementation detail is not explicitly specified:

choose the cleanest production-appropriate implementation that preserves all requirements.

Do not replace the required technology stack.

Do not introduce another framework unnecessarily.

============================================================
67. DO NOT CREATE FAKE FEATURES
============================================================

Do not create buttons that do nothing.

Do not create pages that only contain:

"Coming soon"

Do not create fake AI responses.

Do not create fake authentication.

Do not create fake database data as a replacement for real APIs.

Do not hardcode dashboard numbers.

Dashboard numbers must come from MySQL.

Do not hardcode users.

Users must come from MySQL.

Do not hardcode institutions.

Institutions must come from MySQL.

============================================================
68. ERROR RECOVERY
============================================================

If an error occurs:

1. Identify the root cause.
2. Fix the actual issue.
3. Re-run the failing command.
4. Re-test.
5. Continue only after successful verification.

Do not hide errors.

Do not simply say:

"this should work."

Actually test it.

============================================================
69. FINAL VERIFICATION
============================================================

Before declaring the application complete:

Verify:

Frontend starts.

Backend starts.

MySQL connects.

Health endpoint works.

Login works.

JWT works.

Role protection works.

Tenant isolation works.

Platform Admin works.

College Admin works.

HOD works.

Professor works.

Student works.

Course plans work.

Course plan approval works.

Course plan rejection works.

Revision workflow works.

Versioning works.

Materials work.

Attendance works.

Marks work.

Assignments work.

Announcements work.

Notifications work.

Analytics work.

AI architecture works.

Student AI architecture works.

File security works.

Audit logs work.

Responsive frontend works.

Multi-tenant security tests pass.

============================================================
70. FINAL FOLDER STRUCTURE
============================================================

The final structure should approximately look like:

proprofessor/

├── frontend/
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── layouts/
│   │   ├── routes/
│   │   ├── services/
│   │   ├── context/
│   │   ├── hooks/
│   │   ├── utils/
│   │   └── styles/
│   ├── .env.example
│   ├── package.json
│   └── ...
│
└── backend/
    ├── config/
    │   └── db.js
    │
    ├── controllers/
    │   ├── authController.js
    │   ├── platformController.js
    │   ├── adminController.js
    │   ├── hodController.js
    │   ├── professorController.js
    │   ├── studentController.js
    │   ├── aiController.js
    │   └── ...
    │
    ├── middleware/
    │   ├── authMiddleware.js
    │   ├── roleMiddleware.js
    │   ├── tenantMiddleware.js
    │   ├── uploadMiddleware.js
    │   ├── validationMiddleware.js
    │   └── errorMiddleware.js
    │
    ├── models/
    │   ├── userModel.js
    │   ├── institutionModel.js
    │   ├── departmentModel.js
    │   ├── courseModel.js
    │   ├── coursePlanModel.js
    │   └── ...
    │
    ├── routes/
    │   ├── authRoutes.js
    │   ├── platformRoutes.js
    │   ├── adminRoutes.js
    │   ├── hodRoutes.js
    │   ├── professorRoutes.js
    │   ├── studentRoutes.js
    │   └── ...
    │
    ├── services/
    │   ├── aiService.js
    │   ├── embeddingService.js
    │   ├── vectorSearchService.js
    │   ├── fileService.js
    │   ├── notificationService.js
    │   └── auditService.js
    │
    ├── utils/
    ├── database/
    │   ├── schema.sql
    │   └── seed.sql
    │
    ├── uploads/
    ├── .env.example
    ├── package.json
    └── server.js

============================================================
71. MOST IMPORTANT FINAL RULE
============================================================

This is NOT:

XYZ College software.

This is NOT:

ABC College software.

This is NOT:

two-college software.

This is NOT:

three-college software.

This IS:

ONE PROPROFESSOR AI MULTI-TENANT SaaS PLATFORM.

The platform must be capable of serving:

College 1
College 2
College 3
...
College N

using the SAME:

React frontend
Express backend
MySQL database
authentication system
API system
AI system

with STRICT tenant isolation.

Every institution is dynamically represented by:

institution_id

Every authenticated user's tenant context comes from:

req.user.institution_id

Backend enforces security.

Frontend never provides tenant authorization.

Build this as a serious production-ready MVP.

START NOW.

Inspect the workspace first.

Then implement the COMPLETE APPLICATION.

Do not stop at Phase 1.

Do not stop at Phase 2.

Do not merely explain what should be done.

Actually create the files, code, database schema, APIs, frontend, authentication, authorization, tenant isolation, AI architecture, dashboards, workflows, testing, and documentation.

When finished, provide:

1. Final folder structure
2. Database schema summary
3. API summary
4. Role/permission summary
5. Multi-tenant security summary
6. Seed credentials
7. How to start backend
8. How to start frontend
9. Tests performed
10. Any remaining limitations

DO NOT CLAIM SOMETHING IS IMPLEMENTED UNLESS IT ACTUALLY EXISTS AND HAS BEEN VERIFIED




<!-- admin@proprofessor.local -->
<!-- Password@123 -->
<!-- CSE HOD	csehod@test.com
CSE Professor – DBMS	arun.kumar@test.com
CSE Professor – OS	priya.kumar@test.com
CSE Professor – Networks	rahul.kumar@test.com
CSE Professor – Java	divya.kumar@test.com
CSE Professor – SE	karthik.kumar@test.com
CSE Student – 1st Year A	mohammed@test.com
CSE Student – 1st Year A	ananya@test.com
CSE Student – 1st Year B	arjun@test.com -->


<!-- Test@12345 : password -->