<?php
// profile.php — Admin profile settings
$page_title = 'Profile Settings';
require_once 'includes/header.php';

if(!isset($_SESSION['role'])){
    header('Location: login.php');
    exit;
}

$msg = '';
$msg_type = 'success';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        die('CSRF token mismatch');
    }
    $admin_id = $_SESSION['admin_id'];
    $action = $_POST['action'] ?? 'update_profile';
    
    if($action === 'update_profile'){
        $phone = trim($_POST['phone'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $gmail = trim($_POST['gmail'] ?? '');
        $sql = "UPDATE admins SET phone = ?, location = ?, gmail = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssi', $phone, $location, $gmail, $admin_id);
        if($stmt->execute()){
            $msg = 'Profile updated successfully!';
            $msg_type = 'success';
            $_SESSION['phone'] = $phone;
            $_SESSION['location'] = $location;
            $_SESSION['gmail'] = $gmail;
        } else {
            $msg = 'Error updating profile.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
    
    if($action === 'change_username'){
        $new_username = trim($_POST['new_username'] ?? '');
        if($new_username === ''){
            $msg = 'Username cannot be empty.'; $msg_type='danger';
        } else {
            $stmt = $conn->prepare("UPDATE admins SET username = ? WHERE id = ?");
            $stmt->bind_param('si', $new_username, $admin_id);
            if($stmt->execute()){
                $msg = 'Username updated successfully.'; $msg_type='success';
                $_SESSION['username'] = $new_username;
            } else {
                $msg = 'Error updating username (maybe already taken).'; $msg_type='danger';
            }
            $stmt->close();
        }
    }
    
    if($action === 'change_password'){
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if($new === '' || $new !== $confirm){
            $msg = 'New passwords do not match.'; $msg_type='danger';
        } else {
            // fetch current hash
            $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->bind_param('i', $admin_id);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
            $stmt->close();
            if(!$hash || !password_verify($current, $hash)){
                $msg = 'Current password is incorrect.'; $msg_type='danger';
            } else {
                $newHash = password_hash($new, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $stmt->bind_param('si', $newHash, $admin_id);
                if($stmt->execute()){
                    $msg = 'Password updated successfully.'; $msg_type='success';
                } else {
                    $msg = 'Error updating password.'; $msg_type='danger';
                }
                $stmt->close();
            }
        }
    }
}

// Get current admin data
$admin_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
?>

<div class="container">
    <div class="card">
        <h1><i class="fas fa-user-cog"></i> Profile Settings</h1>
        <p style="color: var(--secondary-color);">Manage your account and contact information</p>
    </div>

    <?php if($msg): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin: 1rem 0;">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($admin['username']) ?>" disabled>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-envelope"></i> Email
                </label>
                <input type="email" class="form-input" value="<?= htmlspecialchars($admin['email']) ?>" disabled>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-phone"></i> Phone Number
                </label>
                <input type="tel" name="phone" class="form-input" 
                       placeholder="Enter your phone number" 
                       value="<?= htmlspecialchars($admin['phone'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-map-marker-alt"></i> Location
                </label>
                <input type="text" name="location" class="form-input" 
                       placeholder="Enter your location" 
                       value="<?= htmlspecialchars($admin['location'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fab fa-google"></i> Gmail Address
                </label>
                <input type="email" name="gmail" class="form-input" 
                       placeholder="Enter your Gmail address" 
                       value="<?= htmlspecialchars($admin['gmail'] ?? '') ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-bottom:1rem;">Login Settings</h2>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="change_username">
                <div class="form-group">
                    <label class="form-label">Change Username</label>
                    <input type="text" name="new_username" class="form-input" value="<?= htmlspecialchars($admin['username']) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-edit"></i> Update Username</button>
            </form>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" minlength="6" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
            </form>
        </div>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark-color);
}

.form-input {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
}
</style>

<?php require_once 'includes/footer.php'; ?>
