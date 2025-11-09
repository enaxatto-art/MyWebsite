<?php
// student_credentials.php — Manage student login credentials
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$page_title = 'Student Login Settings';

// Ensure CSRF token
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

// Ensure password_hash column exists
$col = $conn->query("SHOW COLUMNS FROM students LIKE 'password_hash'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN password_hash VARCHAR(255) NULL AFTER student_id");
}

// Helpers
function find_student_by_id_or_code(mysqli $conn, $idOrCode) {
    if (ctype_digit((string)$idOrCode)) {
        $stmt = $conn->prepare("SELECT id, student_id, full_name, password_hash, status FROM students WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $idOrCode);
    } else {
        $stmt = $conn->prepare("SELECT id, student_id, full_name, password_hash, status FROM students WHERE student_id=? LIMIT 1");
        $stmt->bind_param('s', $idOrCode);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ?: null;
}

$info = '';
$error = '';
$selected_sid = isset($_GET['sid']) ? trim($_GET['sid']) : '';
$student = $selected_sid !== '' ? find_student_by_id_or_code($conn, $selected_sid) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';
        $sid = (int)($_POST['id'] ?? 0);
        $student = find_student_by_id_or_code($conn, $sid);
        if (!$student) {
            $error = 'Student not found.';
        } else if ($action === 'update_student_id') {
            $new_code = strtoupper(trim($_POST['new_student_id'] ?? ''));
            if ($new_code === '') { $error = 'Student ID cannot be empty.'; }
            else {
                $dup = $conn->prepare("SELECT id FROM students WHERE student_id=? AND id<>? LIMIT 1");
                $dup->bind_param('si', $new_code, $sid);
                $dup->execute();
                $exists = $dup->get_result()->fetch_assoc();
                $dup->close();
                if ($exists) { $error = 'Student ID already in use.'; }
                else {
                    $upd = $conn->prepare("UPDATE students SET student_id=? WHERE id=?");
                    $upd->bind_param('si', $new_code, $sid);
                    if ($upd->execute()) { $info = 'Student ID updated.'; $student['student_id'] = $new_code; }
                    else { $error = 'Failed to update Student ID.'; }
                    $upd->close();
                }
            }
        } else if ($action === 'set_password') {
            $new = trim($_POST['new_password'] ?? '');
            $confirm = trim($_POST['confirm_password'] ?? '');
            if ($new === '' || strlen($new) < 6) { $error = 'Password must be at least 6 characters.'; }
            else if ($new !== $confirm) { $error = 'Passwords do not match.'; }
            else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE students SET password_hash=? WHERE id=?");
                $upd->bind_param('si', $hash, $sid);
                if ($upd->execute()) { $info = 'Password updated.'; }
                else { $error = 'Failed to update password.'; }
                $upd->close();
            }
        } else if ($action === 'reset_password') {
            // Generate a temporary password
            $temp = substr(bin2hex(random_bytes(6)), 0, 10);
            $hash = password_hash($temp, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE students SET password_hash=? WHERE id=?");
            $upd->bind_param('si', $hash, $sid);
            if ($upd->execute()) { $info = 'Temporary password generated: ' . htmlspecialchars($temp) . ' (share securely)'; }
            else { $error = 'Failed to reset password.'; }
            $upd->close();
        }
    }
}

// Load active students for picker
$students = [];
$res = $conn->query("SELECT id, student_id, full_name FROM students WHERE status='Active' ORDER BY full_name");
if ($res) { $students = $res->fetch_all(MYSQLI_ASSOC); }

require_once __DIR__ . '/includes/header.php';
echo '<link rel="stylesheet" href="assets/css/report-card.css">';
?>
<div class="container">
  <div class="card" style="margin-bottom:1rem;">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-user-shield"></i> Login Settings</h3>
    <?php if($info): ?><div class="alert alert-success"><?= $info ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <form method="get" class="reports-form" style="margin-bottom:1rem;">
      <select name="sid" class="form-input" style="min-width:280px;">
        <option value="">Select student...</option>
        <?php foreach($students as $s): ?>
        <option value="<?= $s['id'] ?>" <?= ($student && (int)$student['id'] === (int)$s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_id']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Load</button>
    </form>

    <?php if($student): ?>
      <div class="info-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:1rem;">
        <div class="card" style="margin:0;">
          <h4 style="margin-bottom:.5rem;">Change Student ID</h4>
          <form method="post" class="reports-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="update_student_id">
            <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
            <input type="text" name="new_student_id" class="form-input" value="<?= htmlspecialchars($student['student_id']) ?>" placeholder="New Student ID" required>
            <button class="btn btn-primary" type="submit"><i class="fas fa-user-edit"></i> Update ID</button>
          </form>
        </div>
        <div class="card" style="margin:0;">
          <h4 style="margin-bottom:.5rem;">Update Password</h4>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="set_password">
            <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
            <div class="form-group">
              <label class="form-label">New Password</label>
              <input type="password" class="form-input" name="new_password" minlength="6" required autocomplete="new-password">
            </div>
            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input type="password" class="form-input" name="confirm_password" minlength="6" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-key"></i> Update Password</button>
            <button class="btn btn-secondary" type="submit" name="action" value="reset_password" style="margin-left:.5rem;"><i class="fas fa-undo"></i> Reset (Forgot)</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
