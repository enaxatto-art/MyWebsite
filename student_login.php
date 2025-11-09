<?php
// student_login.php — Student login page
session_start();
// Prevent caching so back navigation doesn't show previous input
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/database.php';

// Check if already logged in, redirect if so
if(isset($_SESSION['role'])){
    header('Location: dashboard.php');
    exit;
}

// Check if logged in as student, redirect to student dashboard
if(isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student'){
    header('Location: student_dashboard.php');
    exit;
}

$page_title = 'Student Login';

// Initialize CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg = '';
$msg_type = 'danger';

// Handle logout message
if(isset($_GET['message']) && $_GET['message'] === 'logged_out'){
    $msg = 'You have been successfully logged out.';
    $msg_type = 'success';
}

// Handle login POST request BEFORE including header (to avoid header already sent error)
if($_SERVER['REQUEST_METHOD']==='POST'){
    // CSRF protection
    if(!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
        $msg = 'Security token mismatch. Please try again.';
        $msg_type = 'danger';
    } else {
        $student_code = trim($_POST['student_id'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if(!empty($student_code) && !empty($password)){
            $sql = "SELECT id, student_id, full_name, status, password_hash FROM students WHERE student_id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);

            if($stmt){
                $stmt->bind_param('s', $student_code);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();

                if($row && $row['status'] === 'Active'){
                    // Verify password strictly; if missing hash or mismatch, reject with password-specific message
                    if(!empty($row['password_hash']) && password_verify($password, $row['password_hash'])){
                        $_SESSION["user_type"] = 'student';
                        $_SESSION['student_id'] = (int)$row['id'];
                        $_SESSION['student_code'] = $row['student_id'];
                        $_SESSION['student_name'] = $row['full_name'];
                        header('Location: student_dashboard.php');
                        exit;
                    } else {
                        $msg = 'Password is incorrect.';
                        $msg_type = 'danger';
                    }
                } else {
                    $msg = 'Invalid Student ID or account inactive';
                    $msg_type = 'danger';
                }
                $stmt->close();
            } else {
                $msg = 'Database error. Please try again.';
                $msg_type = 'danger';
            }
        } else {
            $msg = 'Please enter your Student ID and password';
            $msg_type = 'danger';
        }
    }
}

// Now include header AFTER all redirects are handled
require_once 'includes/header.php';
?>

<div class="login-container">
    <div class="login-card">
        <div style="display:flex; justify-content:flex-start; margin-bottom: 0.75rem;">
            <a href="auth.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; border-radius: 10px; text-decoration: none; background: #e2e8f0; color: #1e293b; border: 1px solid #cbd5e1;">
                <i class="fas fa-arrow-left"></i> Back Home
            </a>
        </div>
        <h1 class="login-title">
            <i class="fas fa-graduation-cap"></i>
            Student Portal
        </h1>
        <p class="login-subtitle">Student Login</p>
        
        <?php if($msg): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>
        
        <form method="post" id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label">
                    <i class="fas fa-id-card"></i> Student ID
                </label>
                <input type="text" 
                       name="student_id" 
                       class="form-input" 
                       required 
                       placeholder="Enter your Student ID"
                       autocomplete="off"
                       autocapitalize="none"
                       autocorrect="off"
                       spellcheck="false"
                       autofocus
                       value="">
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div style="position: relative;">
                    <input type="password"
                           name="password"
                           id="studentPassword"
                           class="form-input"
                           placeholder="Enter your password"
                           autocomplete="new-password"
                           autocapitalize="none"
                           autocorrect="off"
                           spellcheck="false"
                           value="">
                    <button type="button" id="togglePass" style="position:absolute; right:8px; top:50%; transform: translateY(-50%); background:#e2e8f0; color:#111827; border:none; border-radius:8px; padding:.35rem .5rem; cursor:pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> 
                Login to Student Dashboard
            </button>
        </form>
        
    </div>
</div>

<script>
// Add some interactive features
// Clear any prefilled values on initial load
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('loginForm');
    if(form){
        // Reset form to clear bfcache or saved entries
        form.reset();
        const sid = form.querySelector('input[name="student_id"]');
        const pw  = form.querySelector('input[name="password"]');
        if(sid) sid.value = '';
        if(pw){ pw.value = ''; pw.type = 'password'; }
    }
});

// Also clear when returning via back/forward cache
window.addEventListener('pageshow', function(e){
    if(e.persisted){
        const form = document.getElementById('loginForm');
        if(form){
            form.reset();
            const sid = form.querySelector('input[name="student_id"]');
            const pw  = form.querySelector('input[name="password"]');
            if(sid) sid.value = '';
            if(pw){ pw.value = ''; pw.type = 'password'; }
        }
    }
});

document.getElementById('loginForm')?.addEventListener('submit', function(e) {
    const button = this.querySelector('button[type="submit"]');
    if(button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        button.disabled = true;
    }
});
const tp = document.getElementById('togglePass');
const pw = document.getElementById('studentPassword');
if (tp && pw) {
    tp.addEventListener('click', function(){
        const isHidden = pw.type === 'password';
        pw.type = isHidden ? 'text' : 'password';
        const icon = this.querySelector('i');
        if(icon){
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>