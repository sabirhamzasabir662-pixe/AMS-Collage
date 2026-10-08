<?php
/**
 * index.php — Entry Point Redirect
 * Arsalan Model School and College Management System
 */
require_once 'database.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
} else {
    header('Location: ' . get_role_dashboard($_SESSION['role'] ?? 'admin'));
}
exit;
