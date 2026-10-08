<?php
/**
 * teachers/delete_teacher.php
 * Arsalan Model School and College Management System
 * Phase 4: Teachers Module
 */

require_once __DIR__ . '/../database.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'Invalid teacher ID specified.');
    redirect('teacher_records.php');
}

// Fetch teacher record to obtain photo filename before deletion
$teacher = db_fetch_one("SELECT id, teacher_id, first_name, last_name, photo FROM teachers WHERE id = ? LIMIT 1", [$id], 'i');

if (!$teacher) {
    set_flash('error', 'Teacher record not found or has already been removed.');
    redirect('teacher_records.php');
}

// 1. Delete photo file from disk if present
if (!empty($teacher['photo'])) {
    $photo_path = __DIR__ . '/../uploads/teachers/' . $teacher['photo'];
    if (file_exists($photo_path) && is_file($photo_path)) {
        @unlink($photo_path);
    }
}

// 2. Delete database record using parameterized prepared statement
try {
    db_execute("DELETE FROM teachers WHERE id = ?", [$id], 'i');
    set_flash('success', "Teacher '{$teacher['first_name']} {$teacher['last_name']}' ({$teacher['teacher_id']}) has been successfully deleted.");
} catch (mysqli_sql_exception $e) {
    set_flash('error', 'Could not delete teacher: related records exist. ' . $e->getMessage());
}

redirect('teacher_records.php');
