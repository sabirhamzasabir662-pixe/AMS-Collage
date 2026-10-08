-- ============================================================
-- Arsalan Model School and College Management System
-- Phase 1 Foundation: Database Setup Script
-- Database: arsalan_model_school
-- Default Admin: username = admin | password = admin123
-- ============================================================

CREATE DATABASE IF NOT EXISTS `arsalan_model_school`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `arsalan_model_school`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. TABLE: users
-- ============================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(50)  NOT NULL,
    `name`       VARCHAR(100) NULL DEFAULT NULL,
    `email`      VARCHAR(100) NULL DEFAULT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','teacher','student') NOT NULL DEFAULT 'admin',
    `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. TABLE: classes
-- ============================================================
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
    `id`               INT         NOT NULL AUTO_INCREMENT,
    `class_name`       VARCHAR(50) NOT NULL,
    `section`          VARCHAR(10) NOT NULL DEFAULT 'A',
    `class_teacher_id`   INT          NULL DEFAULT NULL,
    `class_teacher_name` VARCHAR(100) NULL DEFAULT NULL,
    `room_number`        VARCHAR(20)  NOT NULL DEFAULT '',
    `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. TABLE: subjects
-- ============================================================
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `subject_name` VARCHAR(100) NOT NULL,
    `subject_code` VARCHAR(20)  NOT NULL,
    `class_id`     INT          NOT NULL,
    `teacher_id`   INT          NULL DEFAULT NULL,
    `subject_teacher_name` VARCHAR(100) NULL DEFAULT NULL,
    `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subject_code` (`subject_code`),
    CONSTRAINT `fk_subject_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. TABLE: teachers
-- ============================================================
DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
    `id`            INT            NOT NULL AUTO_INCREMENT,
    `teacher_id`    VARCHAR(20)    NOT NULL,
    `first_name`    VARCHAR(50)    NOT NULL,
    `last_name`     VARCHAR(50)    NOT NULL,
    `gender`        ENUM('Male','Female','Other') NOT NULL,
    `date_of_birth` DATE           NULL,
    `phone`         VARCHAR(15)    NOT NULL,
    `email`         VARCHAR(100)   NOT NULL DEFAULT '',
    `address`       TEXT           NULL,
    `joining_date`  DATE           NULL,
    `subject_id`    INT            NULL DEFAULT NULL,
    `qualification` VARCHAR(100)   NOT NULL DEFAULT '',
    `experience`    INT            NOT NULL DEFAULT 0,
    `salary`        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `photo`         VARCHAR(255)   NULL DEFAULT NULL,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_teacher_id` (`teacher_id`),
    CONSTRAINT `fk_teacher_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add circular foreign keys for teacher references
ALTER TABLE `classes`
    ADD CONSTRAINT `fk_class_teacher` FOREIGN KEY (`class_teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL;

ALTER TABLE `subjects`
    ADD CONSTRAINT `fk_subject_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL;

-- ============================================================
-- 5. TABLE: students
-- ============================================================
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `student_id`     VARCHAR(20)  NOT NULL,
    `first_name`     VARCHAR(50)  NOT NULL,
    `last_name`      VARCHAR(50)  NOT NULL,
    `father_name`    VARCHAR(100) NOT NULL,
    `gender`         ENUM('Male','Female','Other') NOT NULL,
    `date_of_birth`  DATE         NULL,
    `phone`          VARCHAR(15)  NOT NULL,
    `email`          VARCHAR(100) NOT NULL DEFAULT '',
    `address`        TEXT         NULL,
    `class_id`       INT          NOT NULL,
    `admission_date` DATE         NOT NULL,
    `photo`          VARCHAR(255) NULL DEFAULT NULL,
    `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_id` (`student_id`),
    CONSTRAINT `fk_student_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. TABLE: fees
-- ============================================================
DROP TABLE IF EXISTS `fees`;
CREATE TABLE `fees` (
    `id`             INT           NOT NULL AUTO_INCREMENT,
    `student_id`     INT           NOT NULL,
    `class_id`       INT           NOT NULL,
    `fee_month`      VARCHAR(20)   NOT NULL,
    `amount`         DECIMAL(10,2) NOT NULL,
    `payment_date`   DATE          NULL DEFAULT NULL,
    `payment_method` ENUM('Cash','Bank','Online') NOT NULL DEFAULT 'Cash',
    `remarks`        TEXT          NULL,
    `status`         ENUM('paid','pending') NOT NULL DEFAULT 'pending',
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_fee_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fee_class`   FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. TABLE: student_attendance
-- ============================================================
DROP TABLE IF EXISTS `student_attendance`;
CREATE TABLE `student_attendance` (
    `id`              INT  NOT NULL AUTO_INCREMENT,
    `student_id`      INT  NOT NULL,
    `class_id`        INT  NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status`          ENUM('Present','Absent') NOT NULL DEFAULT 'Present',
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sa` (`student_id`, `attendance_date`),
    CONSTRAINT `fk_sa_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sa_class`   FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. TABLE: teacher_attendance
-- ============================================================
DROP TABLE IF EXISTS `teacher_attendance`;
CREATE TABLE `teacher_attendance` (
    `id`              INT  NOT NULL AUTO_INCREMENT,
    `teacher_id`      INT  NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status`          ENUM('Present','Absent') NOT NULL DEFAULT 'Present',
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ta` (`teacher_id`, `attendance_date`),
    CONSTRAINT `fk_ta_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DEFAULT ADMIN USER
-- Username: admin
-- Password: admin123 (BCRYPT hash verified via PHP password_verify)
-- Hash: $2y$10$xEJzdfaOUvVa/GOxRelRO.EEDpQKLCKY9Ookf71o0x9DmrTvVGl8y
-- ============================================================
INSERT INTO `users` (`username`, `name`, `email`, `password`, `role`, `status`)
VALUES ('admin', 'System Administrator', 'admin@arsalan.edu.pk', '$2y$10$xEJzdfaOUvVa/GOxRelRO.EEDpQKLCKY9Ookf71o0x9DmrTvVGl8y', 'admin', 'active')
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `status` = 'active';

-- ============================================================
-- SEED DATA (To populate KPI Cards immediately)
-- ============================================================
-- Classes (All 52 real classes & sections: Nursery through 2nd Year)
INSERT IGNORE INTO `classes` (`id`, `class_name`, `section`, `room_number`, `status`) VALUES
-- Nursery
(1, 'Nursery', 'A', 'N-1', 'active'),
(2, 'Nursery', 'B', 'N-2', 'active'),
(3, 'Nursery', 'C', 'N-3', 'active'),
(4, 'Nursery', 'D', 'N-4', 'active'),
-- KG / Prep
(5, 'KG / Prep', 'A', 'KG-1', 'active'),
(6, 'KG / Prep', 'B', 'KG-2', 'active'),
(7, 'KG / Prep', 'C', 'KG-3', 'active'),
(8, 'KG / Prep', 'D', 'KG-4', 'active'),
-- Class 1
(9, 'Class 1', 'A', '101', 'active'),
(10, 'Class 1', 'B', '102', 'active'),
(11, 'Class 1', 'C', '103', 'active'),
(12, 'Class 1', 'D', '104', 'active'),
-- Class 2
(13, 'Class 2', 'A', '105', 'active'),
(14, 'Class 2', 'B', '106', 'active'),
(15, 'Class 2', 'C', '107', 'active'),
(16, 'Class 2', 'D', '108', 'active'),
-- Class 3
(17, 'Class 3', 'A', '201', 'active'),
(18, 'Class 3', 'B', '202', 'active'),
(19, 'Class 3', 'C', '203', 'active'),
(20, 'Class 3', 'D', '204', 'active'),
-- Class 4
(21, 'Class 4', 'A', '205', 'active'),
(22, 'Class 4', 'B', '206', 'active'),
(23, 'Class 4', 'C', '207', 'active'),
(24, 'Class 4', 'D', '208', 'active'),
-- Class 5
(25, 'Class 5', 'A', '301', 'active'),
(26, 'Class 5', 'B', '302', 'active'),
(27, 'Class 5', 'C', '303', 'active'),
(28, 'Class 5', 'D', '304', 'active'),
-- Class 6
(29, 'Class 6', 'A', '305', 'active'),
(30, 'Class 6', 'B', '306', 'active'),
(31, 'Class 6', 'C', '307', 'active'),
(32, 'Class 6', 'D', '308', 'active'),
-- Class 7
(33, 'Class 7', 'A', '401', 'active'),
(34, 'Class 7', 'B', '402', 'active'),
(35, 'Class 7', 'C', '403', 'active'),
(36, 'Class 7', 'D', '404', 'active'),
-- Class 8
(37, 'Class 8', 'A', '405', 'active'),
(38, 'Class 8', 'B', '406', 'active'),
(39, 'Class 8', 'C', '407', 'active'),
(40, 'Class 8', 'D', '408', 'active'),
-- Class 9
(41, 'Class 9', 'A', '501', 'active'),
(42, 'Class 9', 'B', '502', 'active'),
(43, 'Class 9', 'C', '503', 'active'),
(44, 'Class 9', 'D', '504', 'active'),
-- Class 10
(45, 'Class 10', 'A', '505', 'active'),
(46, 'Class 10', 'B', '506', 'active'),
(47, 'Class 10', 'C', '507', 'active'),
(48, 'Class 10', 'D', '508', 'active'),
-- 1st Year
(49, '1st Year', 'A', '601', 'active'),
(50, '1st Year', 'B', '602', 'active'),
-- 2nd Year
(51, '2nd Year', 'A', '603', 'active'),
(52, '2nd Year', 'B', '604', 'active');

-- Subjects
INSERT IGNORE INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `status`) VALUES
(1, 'English Compulsory', 'ENG-101', 41, 'active'),
(2, 'Mathematics', 'MTH-101', 41, 'active'),
(3, 'Physics', 'PHY-101', 41, 'active'),
(4, 'Chemistry', 'CHM-101', 41, 'active'),
(5, 'Biology', 'BIO-101', 41, 'active'),
(6, 'Computer Science', 'CS-101', 45, 'active'),
(7, 'Urdu', 'URD-101', 41, 'active'),
(8, 'Islamiyat', 'ISL-101', 41, 'active');

-- Teachers
INSERT IGNORE INTO `teachers` (`id`, `teacher_id`, `first_name`, `last_name`, `gender`, `phone`, `email`, `subject_id`, `qualification`, `experience`, `salary`, `status`) VALUES
(1, 'TCH-001', 'Muhammad', 'Tariq', 'Male', '0300-1234567', 'tariq@arsalan.edu.pk', 2, 'M.Sc Mathematics', 8, 45000.00, 'active'),
(2, 'TCH-002', 'Ayesha', 'Khan', 'Female', '0312-9876543', 'ayesha@arsalan.edu.pk', 3, 'M.Sc Physics', 5, 42000.00, 'active'),
(3, 'TCH-003', 'Rashid', 'Mahmood', 'Male', '0333-5554433', 'rashid@arsalan.edu.pk', 1, 'M.A English', 10, 48000.00, 'active');

-- Students
INSERT IGNORE INTO `students` (`id`, `student_id`, `first_name`, `last_name`, `father_name`, `gender`, `phone`, `email`, `class_id`, `admission_date`, `status`) VALUES
(1, 'STD-001', 'Hamza', 'Ali', 'Muhammad Ali', 'Male', '0301-1112233', 'hamza@example.com', 41, '2026-03-01', 'active'),
(2, 'STD-002', 'Fatima', 'Zahra', 'Tariq Mehmood', 'Female', '0302-2223344', 'fatima@example.com', 41, '2026-03-01', 'active'),
(3, 'STD-003', 'Bilal', 'Ahmed', 'Saeed Ahmed', 'Male', '0303-3334455', 'bilal@example.com', 45, '2026-03-05', 'active'),
(4, 'STD-004', 'Zainab', 'Bibi', 'Imran Khan', 'Female', '0304-4445566', 'zainab@example.com', 49, '2026-03-08', 'active');

-- Fees
INSERT IGNORE INTO `fees` (`id`, `student_id`, `class_id`, `fee_month`, `amount`, `payment_date`, `payment_method`, `remarks`, `status`) VALUES
(1, 1, 41, 'September 2026', 3500.00, '2026-09-02', 'Cash', 'Tuition Fee', 'paid'),
(2, 2, 41, 'September 2026', 3500.00, '2026-09-03', 'Online', 'Tuition Fee via Easypaisa', 'paid'),
(3, 3, 45, 'September 2026', 3800.00, '2026-09-05', 'Bank', 'HBL Challan', 'paid'),
(4, 4, 49, 'September 2026', 4500.00, NULL, 'Cash', 'College Fee', 'pending');

SELECT 'Setup successfully completed! Database, 8 tables, default admin, and 52 real classes are ready.' AS `Status`;
