<?php
/**
 * parent_helper.php — Parent Portal Scoping & Helpers
 * Arsalan Model School and College Management System
 *
 * Enforces strict 1:1 parent-to-student mapping.
 * Each parent account maps directly to exactly one student via students.parent_user_id.
 */
require_once __DIR__ . '/../database.php';

/**
 * Retrieve the single student record associated with this parent account.
 *
 * @param int $user_id Parent's user_id from users table
 * @return array|null Student row with class_name and section, or null if unlinked
 */
function get_parent_student(int $user_id): ?array {
    return db_fetch_one(
        "SELECT s.*, c.class_name, c.section, c.id AS class_id 
         FROM students s 
         LEFT JOIN classes c ON s.class_id = c.id 
         WHERE s.parent_user_id = ? 
         LIMIT 1",
        'i',
        [$user_id]
    );
}

/**
 * Verify whether a specific student_id belongs to the logged-in parent.
 *
 * @param int $user_id Parent's user_id
 * @param int $student_id Student's id
 * @return bool True if the student is linked to this parent, false otherwise
 */
function is_parent_of_student(int $user_id, int $student_id): bool {
    $row = db_fetch_one(
        "SELECT id FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1",
        'ii',
        [$student_id, $user_id]
    );
    return !empty($row);
}
