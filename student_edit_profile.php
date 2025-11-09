<?php
// student_edit_profile.php — Student profile editing
$page_title = 'Edit Profile';
require_once 'includes/header.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student'){
    header('Location: student_login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$msg = '';
$msg_type = 'success';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        die('CSRF token mismatch');
    }
    
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $address = trim($_POST['address'] ?? '');
    
    // Validate required fields
    if(empty($full_name) || empty($email) || empty($gender) || empty($dob)){
        $msg = 'Please fill in all required fields.';
        $msg_type = 'danger';
    } else {
        // Check if email is already taken by another student
        $check_sql = "SELECT id FROM students WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('si', $email, $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0){
            $msg = 'Email is already taken by another student.';
            $msg_type = 'danger';
        } else {
            $sql = "UPDATE students SET full_name = ?, email = ?, phone = ?, gender = ?, dob = ?, address = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssssi', $full_name, $email, $phone, $gender, $dob, $address, $student_id);
            
            if($stmt->execute()){
                $msg = 'Profile updated successfully!';
                $msg_type = 'success';
                // Update session
                $_SESSION['student_name'] = $full_name;
                $_SESSION['student_email'] = $email;
            } else {
                $msg = 'Error updating profile.';
                $msg_type = 'danger';
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Get current student data
$sql = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<style>
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.profile-header h1 {
    margin: 0;
    font-size: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark-color);
}

.form-label i {
    margin-right: 0.5rem;
    color: var(--primary-color);
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

.form-input:disabled {
    background: #f1f5f9;
    cursor: not-allowed;
}
</style>

<div class="container">
    <div class="profile-header">
        <h1><i class="fas fa-user-edit"></i> Edit My Profile</h1>
        <p>Update your personal information</p>
    </div>

    <?php if($msg): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin: 1rem 0;">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-id-card"></i> Student ID
                </label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($student['student_id']) ?>" disabled>
                <small style="color: var(--secondary-color);">Student ID cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-user"></i> Full Name *
                </label>
                <input type="text" name="full_name" class="form-input" value="<?= htmlspecialchars($student['full_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-envelope"></i> Email Address *
                </label>
                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($student['email']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-phone"></i> Phone Number
                </label>
                <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($student['phone'] ?? '') ?>" placeholder="Enter your phone number">
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-venus-mars"></i> Gender *
                </label>
                <select name="gender" class="form-input" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?= $student['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $student['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $student['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-birthday-cake"></i> Date of Birth *
                </label>
                <input type="date" name="dob" class="form-input" value="<?= htmlspecialchars($student['dob']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-map-marker-alt"></i> Address
                </label>
                <textarea name="address" class="form-input" rows="3" placeholder="Enter your address"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-calendar"></i> Enrollment Date
                </label>
                <input type="date" class="form-input" value="<?= htmlspecialchars($student['enrollment_date']) ?>" disabled>
                <small style="color: var(--secondary-color);">Enrollment date cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-info-circle"></i> Status
                </label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($student['status']) ?>" disabled>
                <small style="color: var(--secondary-color);">Status cannot be changed by students</small>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="student_dashboard.php" class="btn btn-secondary" style="flex: 1; text-align: center;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Back Button -->
    <div style="text-align: center; margin-top: 2rem;">
        <a href="student_dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

