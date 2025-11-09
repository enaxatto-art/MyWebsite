<?php
$page_title = 'Enroll Course';
require_once 'includes/header.php';

if(!isset($_SESSION['role']) || !in_array($_SESSION['role'],['manager_admin','student_admin'])){
    header('Location: login.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (int)($_POST['student_id'] ?? 0);
if($student_id <= 0){
    echo '<div class="container"><div class="alert alert-danger">Invalid student.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

$success_msg = null; $error_msg = null;

try {
    $conn->query("CREATE TABLE IF NOT EXISTS student_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        enrollment_date DATE NOT NULL,
        status VARCHAR(20) DEFAULT 'Enrolled',
        final_grade VARCHAR(5) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_course (student_id, course_id),
        INDEX(student_id), INDEX(course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Throwable $e){ $error_msg = 'Database setup error: '.htmlspecialchars($e->getMessage()); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){ die('CSRF token mismatch'); }
    $course_id = (int)($_POST['course_id'] ?? 0);
    $enroll_date = $_POST['enrollment_date'] ?? date('Y-m-d');
    if($course_id <= 0){ $error_msg = 'Please select a course.'; }
    if(!$error_msg){
        $chk = $conn->prepare('SELECT id FROM student_enrollments WHERE student_id=? AND course_id=?');
        $chk->bind_param('ii',$student_id,$course_id);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        if($exists){
            $error_msg = 'Student already enrolled in selected course.';
        } else {
            $ins = $conn->prepare('INSERT INTO student_enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, ?)');
            $ins->bind_param('iis',$student_id,$course_id,$enroll_date);
            if($ins->execute()){
                $success_msg = 'Enrollment successful.';
            } else {
                $error_msg = 'Error enrolling: '.htmlspecialchars($conn->error);
            }
        }
    }
}

$stu_stmt = $conn->prepare('SELECT id, student_id AS student_number, full_name FROM students WHERE id=?');
$stu_stmt->bind_param('i',$student_id);
$stu_stmt->execute();
$student = $stu_stmt->get_result()->fetch_assoc();
if(!$student){
    echo '<div class="container"><div class="alert alert-danger">Student not found.</div></div>';
    require_once 'includes/footer.php'; exit;
}

$enrolled_ids = [];
$enr = $conn->prepare('SELECT course_id FROM student_enrollments WHERE student_id=?');
$enr->bind_param('i',$student_id);
$enr->execute();
$resEnr = $enr->get_result();
while($r = $resEnr->fetch_assoc()){ $enrolled_ids[] = (int)$r['course_id']; }

$courses = $conn->query("SELECT id, course_name, course_code, status FROM courses ORDER BY course_name");
?>

<div class="container">
    <div class="card" style="margin-bottom:1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="margin:0;">Enroll Course</h1>
                <div style="color:var(--secondary-color);">Student: <strong><?= htmlspecialchars($student['full_name']) ?></strong> (<?= htmlspecialchars($student['student_number']) ?>)</div>
            </div>
            <div>
                <a href="student.php" class="btn btn-secondary" style="text-decoration:none;">Back to Students</a>
            </div>
        </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="card" style="margin-bottom:1.25rem;">
        <h3 style="margin-top:0;">Select Course</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="student_id" value="<?= (int)$student_id ?>">
            <div class="form-group">
                <label class="form-label">Course</label>
                <select name="course_id" class="form-input" required>
                    <option value="">Select Course</option>
                    <?php while($c = $courses->fetch_assoc()): $cid=(int)$c['id']; ?>
                        <option value="<?= $cid ?>" <?= in_array($cid,$enrolled_ids,true) ? 'disabled' : '' ?>>
                            <?= htmlspecialchars($c['course_name']) ?> (<?= htmlspecialchars($c['course_code']) ?>)<?= $c['status']!=='Active' ? ' - '.$c['status'] : '' ?>
                            <?= in_array($cid,$enrolled_ids,true) ? ' - Already enrolled' : '' ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Enrollment Date</label>
                <input type="date" name="enrollment_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                <a href="student.php" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-primary">Enroll Course</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">All Courses</h3>
        <table class="table data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $courses->data_seek(0);
                while($c = $courses->fetch_assoc()): $cid=(int)$c['id']; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                    <td><?= htmlspecialchars($c['course_name']) ?></td>
                    <td><span class="badge badge-<?= $c['status']==='Active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                    <td style="text-align:right;">
                        <?php if(in_array($cid,$enrolled_ids,true)): ?>
                            <span class="badge badge-primary">Enrolled</span>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="student_id" value="<?= (int)$student_id ?>">
                                <input type="hidden" name="course_id" value="<?= $cid ?>">
                                <input type="hidden" name="enrollment_date" value="<?= date('Y-m-d') ?>">
                                <button type="submit" class="btn btn-sm btn-success">Enroll</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
