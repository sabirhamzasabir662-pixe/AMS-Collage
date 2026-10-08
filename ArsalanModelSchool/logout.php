<?php
/**
 * Arsalan Model School and College Management System
 * Phase 1: Foundation
 * 
 * logout.php — Session Destruction and Redirect
 */

require_once __DIR__ . '/database.php';

// Unset all session variables
$_SESSION = [];

// Delete the session cookie if used
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session on server
session_destroy();

// Redirect back to login page
redirect('login.php');
