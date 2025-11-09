<?php
// auth.php — Login selector page
session_start();

// If already logged in, redirect appropriately
if (isset($_SESSION['role'])) {
    header('Location: dashboard.php');
    exit;
}
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student') {
    header('Location: student_dashboard.php');
    exit;
}

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div style="font-weight:700; color:#a3e635; margin-bottom:.25rem;">(A TECH)</div>
    <div class="auth-title">Login</div>
    <div class="auth-sub">Choose how you want to access</div>
    <a class="btn btn-primary" href="student_login.php"><i class="fas fa-user-graduate"></i> Student Login</a>
    <a class="btn" href="login.php"><i class="fas fa-shield-alt"></i> Admin Login</a>
    <div style="margin-top:.5rem; color:#64748b; font-size:.85rem;">You can switch anytime</div>
  </div>
  <div class="promo">
    <div>
      <div style="opacity:.85;">Welcome</div>
      <h2>Welcome to student portal</h2>
      <p>Login to access your account</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
