<?php
/**
 * students/delete_student.php
 */
require_once '../database.php';
require_login();
require_permission('students.delete');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { set_flash('error', 'Invalid student ID.'); redirect('../students/student_records.php'); }

$student = db_fetch_one("SELECT id, first_name, last_name, student_id, photo FROM students WHERE id=?", 'i', [$id]);
if (!$student) { set_flash('error', 'Student not found.'); redirect('../students/student_records.php'); }

// Delete photo file
if ($student['photo'] && file_exists(STUDENT_PHOTO_DIR . $student['photo'])) {
    @unlink(STUDENT_PHOTO_DIR . $student['photo']);
}

// Delete record (cascades to fees, attendance)
db_execute("DELETE FROM students WHERE id=?", 'i', [$id]);

set_flash('success', 'Student "' . $student['first_name'] . ' ' . $student['last_name'] . '" has been deleted.');
redirect('../students/student_records.php');
