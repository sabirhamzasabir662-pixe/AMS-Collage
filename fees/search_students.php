<?php
/**
 * fees/search_students.php — Lightweight Type-Ahead Search Endpoint for Students
 * Arsalan Model School and College Management System
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/fees_helper.php';
require_login();
if (!has_permission('fees.add') && !has_permission('fees.view') && !has_permission('students.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$query = trim($_GET['q'] ?? '');
if (strlen($query) === 0) {
    echo json_encode([]);
    exit;
}

$like = '%' . $query . '%';

// Prepared statement searching anywhere in student_id, first_name, last_name, or full name
$sql = "SELECT s.id, s.student_id, s.first_name, s.last_name, s.father_name,
               c.id AS class_id, c.class_name, c.section
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.status = 'active'
          AND (s.student_id LIKE ? 
               OR s.first_name LIKE ? 
               OR s.last_name LIKE ? 
               OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)
        ORDER BY s.first_name ASC, s.last_name ASC
        LIMIT 20";

$results = fee_db_fetch_all($sql, [$like, $like, $like, $like], 'ssss');

$output = [];
foreach ($results as $row) {
    $class_name = $row['class_name'] ?? 'No Class';
    $section    = !empty($row['section']) ? ' (Sec ' . $row['section'] . ')' : '';
    $full_name  = $row['first_name'] . ' ' . $row['last_name'];
    $label      = $row['student_id'] . ' — ' . $full_name . ' — ' . $class_name . $section;
    $class_text = $class_name . (!empty($row['section']) ? ' - Section ' . $row['section'] : '');

    $output[] = [
        'id'           => (int)$row['id'],
        'student_id'   => $row['student_id'],
        'first_name'   => $row['first_name'],
        'last_name'    => $row['last_name'],
        'full_name'    => $full_name,
        'father_name'  => $row['father_name'] ?? '',
        'class_id'     => (int)$row['class_id'],
        'class_name'   => $class_text,
        'label'        => $label
    ];
}

echo json_encode($output);
exit;
