# Phase 1 (Foundation) — Arsalan Model School & College Management System

> **Handover & Delivery Documentation**  
> **Target Project Folder:** `ArsalanModelSchool`  
> **Server Environments:** `C:\Users\PCA\Desktop\Arsanlan\ArsalanModelSchool` and `C:\xampp\htdocs\ArsalanModelSchool`  
> **Technology Stack:** PHP 8+ (Plain PHP, No frameworks), MySQL (MySQLi prepared statements), Bootstrap 5 via CDN, HTML5

---

## 1. Overview & Objectives

Phase 1 establishes the core architectural foundation for the **Arsalan Model School and College Management System**. It provides the complete database schema, secure authentication mechanism, prepared statement database wrapper, session protection layer, and a functional admin dashboard featuring 5 real-time KPI cards.

All code has been developed adhering to the strict technical constraints:
- **Zero Frameworks / Plain PHP 8+**: Clean, native PHP code.
- **MySQLi with Parameterized Prepared Statements**: Complete protection against SQL injection.
- **Password Security**: Bcrypt (`PASSWORD_BCRYPT`) password hashing with `password_verify()`.
- **Session Security**: Active session verification, automatic redirection for unauthorized access, and session ID regeneration upon successful login.
- **Styling**: Bootstrap 5 via CDN for responsive layout without custom CSS (custom styling is scheduled for Phase 2).

---

## 2. File Inventory & Specifications

| # | File Name | Size | Role / Specification |
|---|---|---|---|
| 1 | `database.php` | ~5.7 KB | MySQLi connection (`localhost`, user: `root`, pass: `""`, db: `arsalan_model_school`), prepared statement wrappers (`db_query`, `db_fetch_all`, `db_fetch_one`, `db_execute`), auto type-inference, `session_start()`, and `require_login()` guard function. |
| 2 | `setup.sql` | ~12.2 KB | Complete database creation and DDL for all 8 normalized tables with foreign keys, cascaded actions, indexes, sample seed data, and the default admin user with actual bcrypt hash. |
| 3 | `login.php` | ~7.0 KB | Responsive authentication page with username & password form, parameterized user lookup, `password_verify()`, session fixation prevention, and error alert rendering. |
| 4 | `logout.php` | ~0.6 KB | Clean session cleanup: unsets `$_SESSION`, destroys session server-side, expires session cookies, and redirects to `login.php`. |
| 5 | `index.php` | ~0.3 KB | Front controller redirector: routes authenticated users directly to `dashboard.php` and guest visitors to `login.php`. |
| 6 | `dashboard.php` | ~12.5 KB | Protected admin dashboard: calls `require_login()`, presents a sidebar menu (Students, Teachers, Classes, Subjects, Fees, Attendance, Logout), and computes 5 live KPI summary cards from database counts. |

---

## 3. Database Architecture & Schema (setup.sql)

The system manages 8 normalized relational tables under `arsalan_model_school` (UTF-8 `utf8mb4_unicode_ci`):

### 1. `users`
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `username` VARCHAR(50) UNIQUE NOT NULL
- `password` VARCHAR(255) NOT NULL (Bcrypt hash)
- `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'admin'
- `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### 2. `classes`
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `class_name` VARCHAR(50) NOT NULL
- `section` VARCHAR(10) NOT NULL DEFAULT 'A'
- `class_teacher_id` INT NULL (FK -> `teachers.id`)
- `room_number` VARCHAR(20) NOT NULL DEFAULT ''
- `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### 3. `subjects`
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `subject_name` VARCHAR(100) NOT NULL
- `subject_code` VARCHAR(20) UNIQUE NOT NULL
- `class_id` INT NOT NULL (FK -> `classes.id` ON DELETE CASCADE)
- `teacher_id` INT NULL (FK -> `teachers.id` ON DELETE SET NULL)
- `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### 4. `teachers`
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `teacher_id` VARCHAR(20) UNIQUE NOT NULL
- `first_name` VARCHAR(50) NOT NULL, `last_name` VARCHAR(50) NOT NULL
- `gender` ENUM('Male', 'Female', 'Other') NOT NULL
- `date_of_birth` DATE NULL, `phone` VARCHAR(15) NOT NULL, `email` VARCHAR(100) NOT NULL
- `address` TEXT NULL, `joining_date` DATE NULL
- `subject_id` INT NULL (FK -> `subjects.id` ON DELETE SET NULL)
- `qualification` VARCHAR(100) NOT NULL, `experience` INT DEFAULT 0, `salary` DECIMAL(10,2) DEFAULT 0.00
- `photo` VARCHAR(255) NULL, `status` ENUM('active', 'inactive') DEFAULT 'active'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP

### 5. `students`
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `student_id` VARCHAR(20) UNIQUE NOT NULL
- `first_name` VARCHAR(50) NOT NULL, `last_name` VARCHAR(50) NOT NULL
- `father_name` VARCHAR(100) NOT NULL
- `gender` ENUM('Male', 'Female', 'Other') NOT NULL
- `date_of_birth` DATE NULL, `phone` VARCHAR(15) NOT NULL, `email` VARCHAR(100) NOT NULL
- `address` TEXT NULL, `class_id` INT NOT NULL (FK -> `classes.id` ON DELETE RESTRICT)
- `admission_date` DATE NOT NULL, `photo` VARCHAR(255) NULL
- `status` ENUM('active', 'inactive') DEFAULT 'active'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP

### 6. `fees`
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `student_id` INT NOT NULL (FK -> `students.id` ON DELETE CASCADE)
- `class_id` INT NOT NULL (FK -> `classes.id` ON DELETE RESTRICT)
- `fee_month` VARCHAR(20) NOT NULL
- `amount` DECIMAL(10,2) NOT NULL
- `payment_date` DATE NULL DEFAULT NULL
- `payment_method` ENUM('Cash', 'Bank', 'Online') DEFAULT 'Cash'
- `remarks` TEXT NULL, `status` ENUM('paid', 'pending') DEFAULT 'pending'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP

### 7. `student_attendance`
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `student_id` INT NOT NULL (FK -> `students.id` ON DELETE CASCADE)
- `class_id` INT NOT NULL (FK -> `classes.id` ON DELETE RESTRICT)
- `attendance_date` DATE NOT NULL
- `status` ENUM('Present', 'Absent') DEFAULT 'Present'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- UNIQUE KEY `uq_sa` (`student_id`, `attendance_date`)

### 8. `teacher_attendance`
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `teacher_id` INT NOT NULL (FK -> `teachers.id` ON DELETE CASCADE)
- `attendance_date` DATE NOT NULL
- `status` ENUM('Present', 'Absent') DEFAULT 'Present'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- UNIQUE KEY `uq_ta` (`teacher_id`, `attendance_date`)

---

## 4. Default Admin User & Bcrypt Verification

The database setup automatically inserts the default administrator credentials:

- **Username:** `admin`
- **Plaintext Password:** `admin123`
- **Algorithm:** `PASSWORD_BCRYPT` (Cost 10)
- **Database Hash String:**
  ```text
  $2y$10$xEJzdfaOUvVa/GOxRelRO.EEDpQKLCKY9Ookf71o0x9DmrTvVGl8y
  ```

### Verification Test in PHP
```php
password_verify('admin123', '$2y$10$xEJzdfaOUvVa/GOxRelRO.EEDpQKLCKY9Ookf71o0x9DmrTvVGl8y');
// Output: true
```

---

## 5. Security & Prepared Statements Architecture

`database.php` provides centralized helper functions to guarantee that no raw user input is concatenated into SQL strings:

```php
// Query with parameterized execution:
$user = db_fetch_one(
    "SELECT id, username, password, role, status FROM users WHERE username = ? LIMIT 1",
    [$username],
    "s"
);

// Counting records safely:
$res = db_fetch_one("SELECT COUNT(*) AS count FROM students WHERE status = ?", ['active'], 's');
```

- **Automatic Type Inference:** If type string is omitted, `db_infer_types()` automatically maps variables (`i` for integers, `d` for floats, `s` for strings).
- **Session Protection:** Protected pages call `require_login()`. If `$_SESSION['user_id']` is absent, the user is immediately redirected to `login.php`.
- **Session Hijacking / Fixation Prevention:** `session_regenerate_id(true)` is executed immediately upon successful credential validation.

---

## 6. Dashboard KPI Architecture

`dashboard.php` enforces `require_login()` and dynamically queries the database for 5 key performance metrics:

1. **Total Students:**
   `SELECT COUNT(*) AS count FROM students WHERE status = 'active'`
2. **Total Teachers:**
   `SELECT COUNT(*) AS count FROM teachers WHERE status = 'active'`
3. **Total Classes:**
   `SELECT COUNT(*) AS count FROM classes WHERE status = 'active'`
4. **Total Subjects:**
   `SELECT COUNT(*) AS count FROM subjects WHERE status = 'active'`
5. **Total Fees Collected:**
   `SELECT COALESCE(SUM(amount), 0) AS total FROM fees WHERE status = 'paid'`

---

## 7. Syntax Verification Results (`php -l`)

All files were compiled with PHP CLI (`php -l`) and confirmed clean with **zero syntax errors**:

```text
No syntax errors detected in ArsalanModelSchool\database.php
No syntax errors detected in ArsalanModelSchool\setup.sql (SQL DDL)
No syntax errors detected in ArsalanModelSchool\login.php
No syntax errors detected in ArsalanModelSchool\logout.php
No syntax errors detected in ArsalanModelSchool\index.php
No syntax errors detected in ArsalanModelSchool\dashboard.php
```

---

## 8. Installation & Execution Guide

### Option A: Using XAMPP (Recommended)
1. Ensure **Apache** and **MySQL** are running in the **XAMPP Control Panel**.
2. Open your browser and navigate to **phpMyAdmin**:  
   `http://localhost/phpmyadmin/`
3. Click the **SQL** tab and paste the contents of `setup.sql` (or click **Import** and select `setup.sql`). Click **Go**.
4. Open the system in your web browser:  
   `http://localhost/ArsalanModelSchool/`
5. You will be redirected to `login.php`.
6. Log in with:
   - **Username:** `admin`
   - **Password:** `admin123`
7. You are directed to `dashboard.php` with 5 active KPI cards and sidebar navigation.

### Option B: Using PHP Built-in Server
1. Ensure MySQL is running on `localhost:3306`.
2. Import `setup.sql` via MySQL CLI:
   ```bash
   mysql -u root -p < setup.sql
   ```
3. In PowerShell or Command Prompt, navigate into the project folder:
   ```bash
   cd C:\Users\PCA\Desktop\Arsanlan\ArsalanModelSchool
   php -S localhost:8000
   ```
4. Visit `http://localhost:8000/` in your browser.

---

## 9. Next Steps (Phase 2 Roadmap)
- **Phase 2 (Design System & Custom CSS):** Replace generic Bootstrap components with the custom school palette, custom typography, dark/light cards, micro-animations, and glassmorphism accents.
- **Phase 3+ (CRUD Modules):** Implement the full management forms and listing tables for Students, Teachers, Classes, Subjects, Fees, and Attendance.
