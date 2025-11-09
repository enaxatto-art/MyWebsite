<?php
// header.php - Common header for all pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/auth_check.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CSRF Token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="A TECH Portal">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>(A TECH)</title>
    <?php 
        $cssVer1 = file_exists(__DIR__.'/../assets/css/style.css') ? filemtime(__DIR__.'/../assets/css/style.css') : time();
        $cssVer2 = file_exists(__DIR__.'/../assets/css/mobile-app.css') ? filemtime(__DIR__.'/../assets/css/mobile-app.css') : time();
    ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $cssVer1 ?>">
    <link rel="stylesheet" href="assets/css/mobile-app.css?v=<?= $cssVer2 ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/images/icon-192.png">
</head>
<body>
    <!-- Mobile Top Bar (only show if logged in) -->
    <?php if (isset($_SESSION['role']) || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student')): ?>
    <div class="mobile-top-bar">
        <div class="mobile-top-bar-content">
            <button class="mobile-menu-btn" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="mobile-title"><?= isset($page_title) ? $page_title : '(A TECH)' ?></div>
            <div class="mobile-actions">
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student'): ?>
                    <a href="logout.php?type=student" class="btn btn-danger btn-sm" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Drawer -->
    <div class="mobile-nav">
        <div class="mobile-nav-overlay"></div>
        <div class="mobile-nav-drawer">
            <div class="mobile-nav-header">
                <div class="brand">
                    <i class="fas fa-microchip"></i>
                    <?= isset($_SESSION['role']) ? '(A TECH) Admin' : '(A TECH) Student' ?>
                </div>
                <button class="close-btn" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mobile-nav-menu">
                <?php if (isset($_SESSION['role'])): ?>
                    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="student.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student.php' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i>
                        <span>Students</span>
                    </a>
                    <a href="course.php" class="<?= basename($_SERVER['PHP_SELF']) === 'course.php' ? 'active' : '' ?>">
                        <i class="fas fa-book"></i>
                        <span>Courses</span>
                    </a>
                    <a href="assessment.php" class="<?= basename($_SERVER['PHP_SELF']) === 'assessment.php' ? 'active' : '' ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Assessments</span>
                    </a>
                    <a href="student_marks.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_marks.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Marks</span>
                    </a>
                    <a href="fee_payments.php" class="<?= basename($_SERVER['PHP_SELF']) === 'fee_payments.php' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Fee Payments</span>
                    </a>
                    <a href="attendance.php" class="<?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>Attendance</span>
                    </a>
                    <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-lines"></i>
                        <span>Report</span>
                    </a>
                    <a href="notifications.php" class="<?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                    <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student'): ?>
                    <a href="student_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="student_courses.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_courses.php' ? 'active' : '' ?>">
                        <i class="fas fa-book"></i>
                        <span>My Courses</span>
                    </a>
                    <a href="student_marks.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_marks.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>My Marks</span>
                    </a>
                    <a href="student_fee_payments.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_fee_payments.php' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Fee Payments</span>
                    </a>
                    <a href="student_attendance.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_attendance.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>Attendance</span>
                    </a>
                    <a href="student_edit_profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_edit_profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="logout.php?type=student">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['role'])): ?>
    <!-- Admin Navigation (Desktop) -->
    <nav class="navbar">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <!-- Left Side: Brand -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: center; width: 100%;">
                    <div class="navbar-brand" style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-color); font-weight: 600; font-size: 1.25rem;">
                        <i class="fas fa-microchip" style="color: #667eea; font-size: 1.2em;"></i> (A TECH) - Admin
                    </div>
                </div>
                
                <!-- Right Side: Empty for clean header -->
                <div class="navbar-nav" style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Navigation moved to dashboard content area -->
                </div>
            </div>
        </div>
    </nav>
    <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student'): ?>
    <!-- Student Navigation (Desktop) -->
    <nav class="navbar">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <!-- Left Side: Brand -->
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="student_dashboard.php" class="navbar-brand">
                        <i class="fas fa-microchip" style="color: #667eea; font-size: 1.2em;"></i> (A TECH) - Student
                    </a>
                </div>
                
                <!-- Right Side: Student Logout Only -->
                <div class="navbar-nav" style="display: flex; align-items: center; gap: 0.5rem;">
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Desktop Sidebar + Layout Wrapper -->
    <?php if (isset($_SESSION['role']) || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student')): ?>
    <div class="layout">
        <!-- Left Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <div class="logo">
                    <i class="fas fa-microchip"></i>
                </div>
                <div class="portal-text">(A TECH)</div>
                <div class="sidebar-title">
                    <?= isset($_SESSION['role']) ? 'Admin Portal' : 'Student Portal' ?>
                </div>
            </div>
            <nav class="menu">
                <?php if (isset($_SESSION['role'])): ?>
                    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-gauge"></i><span>Dashboard</span>
                    </a>
                    <a href="student.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student.php' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i><span>Students</span>
                    </a>
                    <a href="student_credentials.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_credentials.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-shield"></i><span>Credentials</span>
                    </a>
                    <a href="course.php" class="<?= basename($_SERVER['PHP_SELF']) === 'course.php' ? 'active' : '' ?>">
                        <i class="fas fa-book"></i><span>Courses</span>
                    </a>
                    <a href="assessment.php" class="<?= basename($_SERVER['PHP_SELF']) === 'assessment.php' ? 'active' : '' ?>">
                        <i class="fas fa-clipboard-list"></i><span>Assessments</span>
                    </a>
                    <a href="student_marks.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_marks.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i><span>Marks</span>
                    </a>
                    <a href="attendance.php" class="<?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i><span>Attendance</span>
                    </a>
                    <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-lines"></i><span>Report</span>
                    </a>
                    <a href="fee_payments.php" class="<?= basename($_SERVER['PHP_SELF']) === 'fee_payments.php' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i><span>Payments</span>
                    </a>
                    <a href="notifications.php" class="<?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>">
                        <i class="fas fa-bell"></i><span>Notifications</span>
                    </a>
                    <div class="separator"></div>
                    <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user"></i><span>Profile</span>
                    </a>
                    <a href="logout.php">
                        <i class="fas fa-right-from-bracket"></i><span>Logout</span>
                    </a>
                <?php else: ?>
                    <a href="student_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-gauge"></i><span>Dashboard</span>
                    </a>
                    <a href="student_edit_profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_edit_profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user"></i><span>Profile</span>
                    </a>
                    <a href="student_fee_payments.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_fee_payments.php' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i><span>Payments</span>
                    </a>
                    <a href="student_courses.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_courses.php' ? 'active' : '' ?>">
                        <i class="fas fa-book"></i><span>Courses</span>
                    </a>
                    <a href="student_marks.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_marks.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i><span>Live Results</span>
                    </a>
                    <a href="student_attendance.php" class="<?= basename($_SERVER['PHP_SELF']) === 'student_attendance.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i><span>Attendance</span>
                    </a>
                    <div class="separator"></div>
                    <a href="logout.php">
                        <i class="fas fa-right-from-bracket"></i><span>Logout</span>
                    </a>
                <?php endif; ?>
            </nav>
        </aside>
        <!-- Main Content Start -->
        <div class="layout-content">
    <?php endif; ?>

    <!-- Mobile Bottom Navigation -->
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student'): ?>
    <nav class="mobile-bottom-nav">
        <div class="mobile-bottom-nav-items">
            <a href="student_dashboard.php" class="mobile-bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'student_dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="student_courses.php" class="mobile-bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'student_courses.php' ? 'active' : '' ?>">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
            <a href="student_marks.php" class="mobile-bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'student_marks.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span>Marks</span>
            </a>
            <a href="student_edit_profile.php" class="mobile-bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'student_edit_profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </nav>
    <?php endif; ?>

    <script src="assets/js/mobile-app.js"></script>
