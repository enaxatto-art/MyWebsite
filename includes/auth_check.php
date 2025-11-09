<?php
// auth_check.php - Session validation and authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

// Check session timeout
function checkSessionTimeout() {
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600; // Default 1 hour
        
        // Check if session has timed out
        if (time() - $_SESSION['LAST_ACTIVITY'] > $timeout) {
            // Session expired
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['LAST_ACTIVITY'] = time();
    return true;
}

// Require admin authentication
function requireAdmin() {
    if (!checkSessionTimeout()) {
        header('Location: login.php?timeout=1');
        exit;
    }
    
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager_admin', 'student_admin'])) {
        header('Location: login.php?error=unauthorized');
        exit;
    }
}

// Require student authentication
function requireStudent() {
    if (!checkSessionTimeout()) {
        header('Location: student_login.php?timeout=1');
        exit;
    }
    
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
        header('Location: student_login.php?error=unauthorized');
        exit;
    }
}

// Initialize session activity time if not set
if (!isset($_SESSION['LAST_ACTIVITY'])) {
    $_SESSION['LAST_ACTIVITY'] = time();
}

