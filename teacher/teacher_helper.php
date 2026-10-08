<?php
/**
 * teacher/teacher_helper.php
 * Helper functions for teacher module queries and security scoping
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';

/**
 * Get teacher record linked to a user account
 */
function get_teacher_record(int $user_id): ?array {
    return db_fetch_one("SELECT * FROM teachers WHERE user_id = ? LIMIT 1", 'i', [$user_id]);
}

/**
 * Fetch all classes assigned to this teacher with student counts
 */
function get_teacher_assigned_classes(int $user_id): array {
    $sql = "SELECT c.id, c.class_name, c.section, c.room_number,
                   COUNT(s.id) AS student_count
            FROM teacher_class_assignments tca
            JOIN classes c ON tca.class_id = c.id
            LEFT JOIN students s ON s.class_id = c.id AND s.status = 'active'
            WHERE tca.teacher_user_id = ? AND c.status = 'active'
            GROUP BY c.id, c.class_name, c.section, c.room_number
            ORDER BY c.class_name ASC, c.section ASC";
    return db_fetch_all($sql, 'i', [$user_id]);
}

/**
 * Return array of class IDs assigned to this teacher
 */
function get_teacher_assigned_class_ids(int $user_id): array {
    $sql = "SELECT class_id FROM teacher_class_assignments WHERE teacher_user_id = ?";
    $rows = db_fetch_all($sql, 'i', [$user_id]);
    return array_map('intval', array_column($rows, 'class_id'));
}

/**
 * Strictly verify whether this teacher is assigned to a specific class_id
 */
function is_teacher_assigned_to_class(int $user_id, int $class_id): bool {
    if ($class_id <= 0) return false;
    $row = db_fetch_one(
        "SELECT 1 FROM teacher_class_assignments WHERE teacher_user_id = ? AND class_id = ? LIMIT 1",
        'ii',
        [$user_id, $class_id]
    );
    return !empty($row);
}

/**
 * Strictly verify whether a student belongs to one of this teacher's assigned classes
 */
function is_student_in_teacher_classes(int $user_id, int $student_id): bool {
    if ($student_id <= 0) return false;
    $sql = "SELECT 1 
            FROM students s
            JOIN teacher_class_assignments tca ON s.class_id = tca.class_id
            WHERE tca.teacher_user_id = ? AND s.id = ?
            LIMIT 1";
    $row = db_fetch_one($sql, 'ii', [$user_id, $student_id]);
    return !empty($row);
}
