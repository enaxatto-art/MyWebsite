<?php
// logout.php - Secure logout functionality
session_start();

// Check if this is a student logout
$is_student = isset($_GET['type']) && $_GET['type'] === 'student';

// Clear all session data
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect based on user type
if ($is_student) {
    header('Location: student_login.php?message=logged_out');
} else {
    header('Location: login.php?message=logged_out');
}
exit;
?>
