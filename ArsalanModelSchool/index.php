<?php
/**
 * Arsalan Model School and College Management System
 * Phase 1: Foundation
 * 
 * index.php — Root Entry Point Redirector
 */

require_once __DIR__ . '/database.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
