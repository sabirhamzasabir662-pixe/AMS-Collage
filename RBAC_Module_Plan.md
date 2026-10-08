# Arsalan Model School & College — Role-Based Access Control Plan

**Status:** Planning document — no code should be written from this until it is approved.
**Depends on:** Role Phase A (already built: `role` ENUM on `users`, `teacher_class_assignments` table, `marks` table, `require_role()` helper, per-role placeholder dashboards).
**Purpose:** Define exactly what each of the 4 roles can and cannot do, how accounts are created, how access is enforced, and in what order the remaining work should be built — before any more code is written.

---

## 1. The 4 Roles at a Glance

| Role | Who | Logs in as | Scope |
|---|---|---|---|
| **Admin** | Principal / Coordinator | 1 account (existing) | Everything — full control of all 4 modules |
| **Teacher** | Teaching staff | 1 account per teacher | Only their **assigned classes** |
| **Parent** | Guardian of a student | 1 account per student (auto-created) | Only their **own child's** data, read-only |
| **Finance** | Accounts/fee staff | 1+ accounts | Only the **Fees module** |

**Core rule (from the RBAC research):** every restriction must be enforced on the **server**, at the top of every PHP file, using `require_role([...])`. Hiding a sidebar link is never enough — a blocked role must be redirected away even if they type the URL directly.

---

## 2. Database Foundation (already built in Phase A — recap)

| Table | Purpose |
|---|---|
| `users.role` | ENUM: `admin`, `teacher`, `parent`, `finance` |
| `teachers.user_id` | Links a teacher's login to their staff record |
| `students.parent_user_id` | Links a student to their parent's login |
| `teacher_class_assignments` | Which classes a specific teacher account can access |
| `marks` | Student marks/results, entered by a teacher |

### New table needed for this plan:

**`activity_log`**
| Column | Type | Notes |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | |
| `user_id` | INT, FK → `users.id` | Who did it |
| `role` | VARCHAR(20) | Role at time of action (in case role changes later) |
| `action` | VARCHAR(255) | e.g. "Marked attendance", "Edited fee record" |
| `target_type` | VARCHAR(50) | e.g. "student", "fee", "class" |
| `target_id` | INT NULL | ID of the affected record |
| `details` | TEXT NULL | Optional extra context |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

Every module below lists which actions get logged here.

---

## 3. MODULE: Admin

**Who:** Principal / Coordinator (existing `admin` account).
**Scope:** Full, unrestricted access to everything — the only role that can create/edit/delete other users.

### Pages & Capabilities
- Full existing dashboard, Students, Teachers, Classes, Subjects, Fees, Attendance, Report, Profile, Settings — unchanged.
- **New: User Management page** (`admin/users.php`)
  - List all accounts across all 4 roles, with role, status (active/inactive), and linked record (teacher name / student name for parents).
  - **Create Teacher account:** pick an existing teacher from `teachers` table (or create new), set username/password, then assign one or more classes via `teacher_class_assignments`.
  - **Create Finance account:** simple form — name, username, password. No class/student assignment needed.
  - **View/edit Parent accounts:** these are auto-created (see Parent module below), but Admin can reset a parent's password or deactivate one if needed.
  - **Deactivate / reactivate** any account (soft-disable, not delete — preserves history).
  - **Reset password** for any user.
- **New: Activity Log viewer** (`admin/activity_log.php`)
  - Filterable table: by user, by role, by date range, by action type.
  - Read-only — this is an audit trail, never editable.

### What gets logged for Admin
- Creating/deactivating any account, resetting any password, editing any role assignment.

### Enforcement
`require_role(['admin'])` at the top of every admin-only file (User Management, Activity Log). All existing modules keep working exactly as today for the admin account.

---

## 4. MODULE: Teacher

**Who:** Teaching staff, one account per teacher.
**Scope:** Only the classes assigned to them by Admin. Cannot see other teachers' classes, cannot touch Fees, Finance, or User Management.

### Pages & Capabilities
- **Teacher Dashboard** (`teacher/teacher_dashboard.php`) — replaces the current placeholder. Shows: their assigned classes, a quick count of students in each, and a shortcut to Mark Attendance / Enter Marks.
- **My Classes** (`teacher/my_classes.php`) — list of only the classes in `teacher_class_assignments` for this teacher's `user_id`. Clicking a class shows its student roster (read view, no edit).
- **Mark Attendance** (`teacher/attendance.php`) — same UI pattern as the existing admin attendance screens, but:
  - The Class dropdown only shows classes this teacher is assigned to.
  - Cannot mark attendance for a class not in their assignment list, even by editing the URL (server-side check against `teacher_class_assignments`).
- **Enter Marks** (`teacher/marks.php`) — new screen:
  - Select one of their assigned classes → select a subject → select a term (e.g. "Midterm 2026") → enter marks per student (marks obtained / total marks) + optional remarks.
  - Can edit marks they previously entered for their own classes; cannot see or edit marks for classes they're not assigned to.
- **Student Records (read-only)** (`teacher/students.php`) — view student profile info (name, father's name, DOB, contact, photo) for students **only within their assigned classes**. No add/edit/delete — that stays Admin-only.

### What Teachers CANNOT do
- Cannot see Fees, Finance data, other teachers' classes, User Management, or Activity Log.
- Cannot edit student personal records (name, phone, address, etc.) — view only.
- Cannot mark attendance or enter marks for a class outside their assignment, even via direct URL.

### What gets logged for Teacher
- "Marked attendance for Class X on [date]"
- "Entered/updated marks for Class X, Subject Y, Term Z"

### Enforcement
Every file in `teacher/` starts with `require_role(['teacher'])`, followed by a second check: does the requested `class_id` exist in this teacher's `teacher_class_assignments`? If not — redirect with an "not authorized for this class" message, don't just hide the option.

---

## 5. MODULE: Student & Parent

**Who:** One parent login per enrolled student (auto-created at enrollment, as already decided).
**Scope:** Read-only. Only their own child's data. If a parent has multiple children enrolled, they can switch between them (see below).

### Account creation (already decided, recap)
- When a student is added in `add_student.php`, the system auto-creates a `users` row: `role = 'parent'`, `username = <Student ID>` (e.g. `STU-011`), a randomly generated default password.
- The new user's `id` is stored in `students.parent_user_id`.
- The default password is shown once to Admin at creation time (e.g. in a confirmation box) so it can be handed to the parent — it is never emailed/SMS'd automatically unless that's added later.
- Parent is required to change their password on first login (see Section 7).

### Multiple children under one parent
- **Decision needed:** if two siblings both enroll, do they get two separate parent logins (one per child), or should Admin be able to link a second child to an existing parent account?
- **Recommended default** (industry-standard pattern): support linking — add an optional "Link to existing parent account" option in `add_student.php` so one parent can log in once and see all their children, rather than juggling multiple logins. If you'd rather keep it simple (one login per child, no linking) for now, that's also fine — just confirm which.

### Pages & Capabilities
- **Parent Dashboard** (`parent/parent_dashboard.php`) — replaces the placeholder. If linked to multiple children, shows a child-switcher at the top; otherwise shows the one child directly.
- **Attendance** (`parent/attendance.php`) — calendar or table view of their child's attendance history, with the same percentage calculation used elsewhere in the app. Read-only.
- **Results / Marks** (`parent/results.php`) — table of marks entered by teachers, grouped by term and subject. Read-only.
- **Student Profile** (`parent/profile.php`) — view (not edit) their child's stored info: class, section, admission date, status.
- **Fee Status** (`parent/fees.php`) — read-only view of their child's fee history: paid/pending per month, no ability to record a new payment (that's Finance's job) — this was requested in your original 4-module description ("childern status").

### What Parents CANNOT do
- Cannot edit anything — the entire module is read-only.
- Cannot see any other student's data, any teacher data, or any admin/finance screens.
- Cannot record or edit fee payments.

### What gets logged for Parent
- Login events only (parents aren't changing data, so there's little else worth logging — can be revisited later if needed).

### Enforcement
Every file in `parent/` starts with `require_role(['parent'])`, then filters every query by `WHERE student_id = (student linked to this parent's user_id)` — never trust a `student_id` passed in the URL without checking it belongs to this parent.

---

## 6. MODULE: Finance

**Who:** Accounts/fee office staff, account(s) created manually by Admin (same pattern as Teacher accounts, minus the class assignment step).
**Scope:** Only the Fees module. Per your answer, Finance's job is strictly to **add fees, save fee records, and handle other finance problems** — not to browse student profiles.

### Pages & Capabilities
- **Finance Dashboard** (`finance/finance_dashboard.php`) — replaces the placeholder. Shows fee summary cards (total collected, total pending, today's collections) — same style as the existing dashboard KPIs.
- **Add Fee** — reuses the existing `fees/add_fee.php` logic (student type-ahead search, auto-fill, Save / Save & Print), but the page itself lives under `finance/` or is shared with `require_role(['admin','finance'])` so both roles can use the same file.
- **Fee Records** — reuses `fees/fee_records.php` (search, filters, totals, Edit/Delete/Print), same dual-role access as above.
- **Print Receipts** — reuses `fees/print_receipt.php`.
- Per your answer, the student type-ahead search in Add Fee only needs to resolve "Student ID — Name — Class" for the purpose of attaching a fee to the right student — it does not open a full student profile page. Finance never gets a "Students" menu item at all.

### What Finance CANNOT do
- Cannot open Students, Teachers, Classes, Subjects, Attendance, User Management, or Activity Log.
- Cannot edit a student's personal information.

### What gets logged for Finance
- "Added fee record #X for Student Y"
- "Edited fee record #X"
- "Deleted fee record #X"
- "Printed receipt for fee #X"

### Enforcement
Since Finance reuses the same `fees/*.php` files as Admin, those files change from `require_login()` to `require_role(['admin','finance'])`. Every other module's files (Students, Teachers, etc.) explicitly exclude `finance` from their `require_role([...])` list.

---

## 7. Cross-Cutting Features (apply to all roles)

### 7.1 Self-service password change
- Every role gets a "Change Password" option in their own dashboard/profile page (Teacher, Parent, Finance each get a minimal version; Admin already has this via `profile.php`).
- Must require entering the current password before setting a new one, same pattern as the existing Admin profile page.
- **Forced change on first login:** Parent accounts (and optionally Teacher/Finance accounts created by Admin with a temporary password) should be flagged `must_change_password = 1` in `users`, and redirected to a mandatory password-change screen before reaching their dashboard on first login.

### 7.2 Activity Log
- A single shared `activity_log` table (Section 2) records the actions listed under each module above.
- Only Admin can view it, via `admin/activity_log.php`.
- Logging should be a single helper function, e.g. `log_activity($user_id, $role, $action, $target_type, $target_id, $details)`, called from inside each existing save/update/delete handler — not a new module to build from scratch, just one line added to existing handlers.

### 7.3 Enforcement pattern (applies everywhere)
Every PHP file, without exception, must start with:
```php
require_login();
require_role(['admin']);          // or whichever roles are allowed for this file
```
No file should rely on the sidebar menu alone to hide functionality from a role. This was the single most important lesson from the research in Section "why this matters."

---

## 8. Build Order (once this plan is approved)

This breaks the remaining work into safe, testable phases — same approach that's worked well so far.

| Phase | What gets built | Depends on |
|---|---|---|
| **B1** | `activity_log` table + `log_activity()` helper + Admin's User Management page (create Teacher/Finance accounts, assign classes, reset passwords, deactivate accounts) | Phase A (done) |
| **B2** | Auto-create Parent account on student add + forced password-change-on-first-login flow for all roles | B1 |
| **C** | Teacher module: dashboard, My Classes, restricted Attendance, Marks Entry, read-only Student view | B1, B2 |
| **D** | Parent module: dashboard (+ child-switcher if multi-child linking is confirmed), Attendance view, Results view, Profile view, Fee Status view | B2 |
| **E** | Finance module: dashboard, reuse of Add Fee / Fee Records / Print Receipt under `require_role(['admin','finance'])` | B1 |
| **F** | Activity Log viewer for Admin + verification pass: log in as each role and confirm every restricted URL correctly blocks that role | B1–E |

Each phase should end with the same discipline used for the earlier bug: after any change, **re-test the pages that weren't supposed to be touched**, not just the new ones — this is what caught the last regression late instead of immediately.

---

## 9. Open Decision Before Building B2

One thing from Section 5 needs your confirmation before Phase B2 is written:

> **Should one parent be able to be linked to multiple children (siblings), or should every student always get their own separate parent login even if they share a parent?**

Everything else in this document is ready to build once you approve it.
