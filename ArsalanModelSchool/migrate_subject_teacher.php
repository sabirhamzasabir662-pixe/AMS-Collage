<?php
require 'database.php';
$db = get_db();
// Add column if not exists
$db->query('ALTER TABLE subjects ADD COLUMN IF NOT EXISTS subject_teacher_name VARCHAR(100) NULL');
// Describe table
if ($result = $db->query('DESCRIBE subjects')) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\t" . $row['Type'] . "\t" . $row['Null'] . "\t" . $row['Key'] . "\t" . $row['Default'] . "\t" . $row['Extra'] . "\n";
    }
} else {
    echo "Error: " . $db->error . "\n";
}
?>
