<?php
/**
 * students/delete_student.php
 * Arsalan Model School and College Management System
 * Phase 3: Students Module
 */

require_once __DIR__ . '/../database.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'Invalid student ID specified.');
    redirect('student_records.php');
}

// Fetch student record to obtain photo filename before deletion
$student = db_fetch_one("SELECT id, student_id, first_name, last_name, photo FROM students WHERE id = ? LIMIT 1", [$id], 'i');

if (!$student) {
    set_flash('error', 'Student record not found or has already been removed.');
    redirect('student_records.php');
}

// 1. Delete photo file from disk if present
if (!empty($student['photo'])) {
    $photo_path = __DIR__ . '/../uploads/students/' . $student['photo'];
    if (file_exists($photo_path) && is_file($photo_path)) {
        @unlink($photo_path);
    }
}

// 2. Delete database record using parameterized prepared statement
try {
    db_execute("DELETE FROM students WHERE id = ?", [$id], 'i');
    set_flash('success', "Student '{$student['first_name']} {$student['last_name']}' ({$student['student_id']}) has been successfully deleted.");
} catch (mysqli_sql_exception $e) {
    // If foreign key constraint restricts deletion (e.g. fees or attendance referencing student)
    set_flash('error', 'Could not delete student: related records (fees/attendance) exist. ' . $e->getMessage());
}

redirect('student_records.php');
