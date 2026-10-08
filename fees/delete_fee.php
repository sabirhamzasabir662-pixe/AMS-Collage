<?php
/**
 * fees/delete_fee.php — Delete Fee Record
 * Arsalan Model School and College Management System
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/fees_helper.php';
require_login();
require_permission('fees.delete');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    set_flash('error', 'Invalid fee record ID.');
    redirect('fee_records.php');
}

$exists = fee_db_fetch_one("SELECT id FROM fees WHERE id = ?", [$id], 'i');
if (!$exists) {
    set_flash('error', 'Fee record not found.');
    redirect('fee_records.php');
}

fee_db_execute("DELETE FROM fees WHERE id = ?", [$id], 'i');
set_flash('success', 'Fee record deleted successfully.');
redirect('fee_records.php');
