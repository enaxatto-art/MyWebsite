<?php
// login.php — Modern admin login page
session_start();
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

$page_title = 'Login';

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
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if(!empty($username) && !empty($password)){
            $sql = "SELECT * FROM admins WHERE username=? LIMIT 1";
            $stmt = $conn->prepare($sql);
            
            if($stmt){
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                
                if($row){
                    if(password_verify($password, $row['password'])){
                        $_SESSION['admin_id'] = $row['id'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['full_name'] = $row['full_name'];
                        header('Location: dashboard.php');
                        exit;
                    } else { 
                        $msg = 'Invalid username or password'; 
                        $msg_type = 'danger';
                    }
                } else { 
                    $msg = 'Invalid username or password'; 
                    $msg_type = 'danger';
                }
                $stmt->close();
            } else {
                $msg = 'Database error. Please try again.';
                $msg_type = 'danger';
            }
        } else {
            $msg = 'Please enter both username and password';
            $msg_type = 'danger';
        }
    }
}

// Now include header AFTER all redirects are handled
require_once 'includes/header.php';
?>

<style>
/* Enhanced Login Styles */
body {
    background: linear-gradient(135deg, #000000 0%, #ff4da6 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    position: relative;
    overflow: hidden;
}

/* Animated background shapes */
body::before,
body::after {
    content: '';
    position: absolute;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.3;
    animation: float 20s ease-in-out infinite;
}

body::before {
    background: rgba(102, 126, 234, 0.5);
    top: 10%;
    left: 10%;
}

body::after {
    background: rgba(118, 75, 162, 0.5);
    bottom: 10%;
    right: 10%;
    animation-delay: -10s;
}

@keyframes gradientShift {
    0% { 
        background-position: 0% 50%; 
    }
    25% { 
        background-position: 100% 50%; 
    }
    50% { 
        background-position: 100% 100%; 
    }
    75% { 
        background-position: 0% 100%; 
    }
    100% { 
        background-position: 0% 50%; 
    }
}

@keyframes float {
    0%, 100% { 
        transform: translate(0, 0) scale(1);
    }
    33% { 
        transform: translate(30px, -30px) scale(1.1);
    }
    66% { 
        transform: translate(-20px, 20px) scale(0.9);
    }
}

.login-container {
    width: 100%;
    max-width: 420px;
}

.login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    padding: 3rem 2.5rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    overflow: hidden;
    width: 380px; /* lock card width */
    max-width: 380px;
}

.login-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
}

.login-title {
    text-align: center;
    margin-bottom: 0.5rem;
    color: #1e293b;
    font-size: 2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.login-title i {
    color: #667eea;
    font-size: 2.2rem;
}

.login-subtitle {
    text-align: center;
    color: #64748b;
    margin-bottom: 2rem;
    font-weight: 500;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #334155;
    font-size: 0.875rem;
}

.form-label i {
    margin-right: 0.5rem;
    color: #667eea;
}

.form-input {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.form-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    transform: none; /* keep form fixed */
}

.form-input::placeholder {
    color: #94a3b8;
}

.btn-login {
    width: 100%;
    padding: 0.875rem 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-login:hover {
    transform: none; /* prevent movement on hover */
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
}

.btn-login:active {
    transform: none; /* prevent movement on active */
}

.alert {
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-danger {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}

</style>

<div class="login-container">
    <div class="login-card">
        <div style="display:flex; justify-content:flex-start; margin-bottom: 0.75rem;">
            <a href="auth.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; border-radius: 10px; text-decoration: none; background: #e2e8f0; color: #1e293b; border: 1px solid #cbd5e1;">
                <i class="fas fa-arrow-left"></i> Back Home
            </a>
        </div>
        <h1 class="login-title">
            <i class="fas fa-graduation-cap"></i>
            Admin Portal
        </h1>
        <p class="login-subtitle">Admin Login</p>
        
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
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" 
                       name="username" 
                       class="form-input" 
                       required 
                       placeholder="Enter your username"
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
                <input type="password" 
                       name="password" 
                       class="form-input" 
                       required 
                       placeholder="Enter your password" 
                       autocomplete="new-password"
                       autocapitalize="none"
                       autocorrect="off"
                       spellcheck="false">
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> 
                Login to Dashboard
            </button>
        </form>
        
    </div>
</div>

<script>
// Add some interactive features
document.getElementById('loginForm')?.addEventListener('submit', function(e) {
    const button = this.querySelector('button[type="submit"]');
    if(button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        button.disabled = true;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>