<?php
/**
 * teachers/delete_teacher.php
 */
require_once '../database.php';
require_login();
require_permission('teachers.delete');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { set_flash('error','Invalid ID.'); redirect('../teachers/teacher_records.php'); }

$teacher = db_fetch_one("SELECT id, first_name, last_name, photo FROM teachers WHERE id=?", 'i', [$id]);
if (!$teacher) { set_flash('error','Teacher not found.'); redirect('../teachers/teacher_records.php'); }

if ($teacher['photo'] && file_exists(TEACHER_PHOTO_DIR . $teacher['photo'])) {
    @unlink(TEACHER_PHOTO_DIR . $teacher['photo']);
}

db_execute("DELETE FROM teachers WHERE id=?", 'i', [$id]);
set_flash('success', 'Teacher "' . $teacher['first_name'] . ' ' . $teacher['last_name'] . '" deleted.');
redirect('../teachers/teacher_records.php');
