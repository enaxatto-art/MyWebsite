<?php
// attendance.php — Attendance management (Admin)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin(); // Use the authentication check function

// Check if attendance table exists, redirect to setup if not
$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
if($table_check && $table_check->num_rows == 0){
    header('Location: setup_tables.php');
    exit;
}

// Ensure required columns exist to avoid SQL errors
$col_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'recorded_by'");
if($col_check && $col_check->num_rows == 0){
    // Add the missing column; make it nullable to avoid failing on existing rows
    $conn->query("ALTER TABLE attendance ADD COLUMN recorded_by INT NULL AFTER remarks");
    // Optionally add foreign key if admins table exists (ignore errors silently)
    @$conn->query("ALTER TABLE attendance ADD CONSTRAINT fk_attendance_recorded_by FOREIGN KEY (recorded_by) REFERENCES admins(id)");
}

$page_title = 'Attendance';
require_once __DIR__ . '/includes/header.php';

$is_manager = isset($_SESSION['role']) && in_array($_SESSION['role'], ['manager_admin','student_admin'], true);
$msg = '';
$msg_type = 'success';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    if($action === 'mark_attendance'){
        $course_id = intval($_POST['course_id']);
        $attendance_date = $_POST['attendance_date'];
        $students_data = $_POST['students'] ?? [];
        $recorded_by = $_SESSION['admin_id'];
        
        $success_count = 0;
        $error_count = 0;
        
        foreach($students_data as $student_id => $data){
            $student_id = intval($student_id);
            $status = $data['status'] ?? 'Absent';
            $remarks = trim($data['remarks'] ?? '');
            
            // Check if attendance already exists for this student, course, and date
            $check_sql = "SELECT id FROM attendance WHERE student_id = ? AND course_id = ? AND attendance_date = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('iis', $student_id, $course_id, $attendance_date);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
            
            if($exists){
                // Update existing record
                $sql = "UPDATE attendance SET status = ?, remarks = ?, recorded_by = ?, created_at = NOW() 
                        WHERE student_id = ? AND course_id = ? AND attendance_date = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssiisi', $status, $remarks, $recorded_by, $student_id, $course_id, $attendance_date);
            } else {
                // Insert new record
                $sql = "INSERT INTO attendance (student_id, course_id, attendance_date, status, remarks, recorded_by) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iisssi', $student_id, $course_id, $attendance_date, $status, $remarks, $recorded_by);
            }
            
            if($stmt->execute()){
                $success_count++;
            } else {
                $error_count++;
            }
            $stmt->close();
        }
        
        if($success_count > 0){
            $msg = "Attendance marked successfully for {$success_count} student(s)!";
            if($error_count > 0){
                $msg .= " {$error_count} error(s) occurred.";
            }
        } else {
            $msg = 'Error marking attendance.';
            $msg_type = 'danger';
        }
    }
    
    if($action === 'update_attendance' && $is_manager){
        $attendance_id = intval($_POST['attendance_id']);
        $status = $_POST['status'];
        $remarks = trim($_POST['remarks'] ?? '');
        
        $sql = "UPDATE attendance SET status = ?, remarks = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $status, $remarks, $attendance_id);
        
        if($stmt->execute()){
            $msg = 'Attendance updated successfully!';
        } else {
            $msg = 'Error updating attendance.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
    
    // Add a single attendance record from header button modal
    if($action === 'add_single_attendance' && $is_manager){
        $student_id = intval($_POST['student_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'Present';
        $remarks = trim($_POST['remarks'] ?? '');
        $recorded_by = $_SESSION['admin_id'];

        if($student_id && $course_id && $attendance_date){
            // Upsert: if record exists for same student/course/date, update; else insert
            $check_sql = "SELECT id FROM attendance WHERE student_id=? AND course_id=? AND attendance_date=?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('iis', $student_id, $course_id, $attendance_date);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if($exists){
                $upd = $conn->prepare("UPDATE attendance SET status=?, remarks=?, recorded_by=? WHERE id=?");
                $upd->bind_param('ssii', $status, $remarks, $recorded_by, $exists['id']);
                if($upd->execute()){
                    $msg = 'Attendance updated successfully!';
                } else {
                    $msg = 'Error updating attendance record.'; $msg_type = 'danger';
                }
                $upd->close();
            } else {
                $ins = $conn->prepare("INSERT INTO attendance (student_id, course_id, attendance_date, status, remarks, recorded_by) VALUES (?,?,?,?,?,?)");
                $ins->bind_param('iisssi', $student_id, $course_id, $attendance_date, $status, $remarks, $recorded_by);
                if($ins->execute()){
                    $msg = 'Attendance added successfully!';
                } else {
                    $msg = 'Error adding attendance record.'; $msg_type = 'danger';
                }
                $ins->close();
            }
        } else {
            $msg = 'Please select student, course and date.'; $msg_type = 'danger';
        }
    }
    
    if($action === 'delete_attendance' && $is_manager){
        $attendance_id = intval($_POST['attendance_id']);
        $sql = "DELETE FROM attendance WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $attendance_id);
        
        if($stmt->execute()){
            $msg = 'Attendance record deleted successfully!';
        } else {
            $msg = 'Error deleting attendance.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}

// Get filter parameters
$filter_course = $_GET['course_id'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_student = $_GET['student_id'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

if($filter_course){
    $where[] = "a.course_id = ?";
    $params[] = intval($filter_course);
    $types .= 'i';
}

if($filter_date){
    $where[] = "a.attendance_date = ?";
    $params[] = $filter_date;
    $types .= 's';
}

if($filter_student){
    $where[] = "a.student_id = ?";
    $params[] = intval($filter_student);
    $types .= 'i';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get attendance records
$sql = "SELECT a.*, s.full_name as student_name, s.student_id as student_code, 
               c.course_name, c.course_code, admin.full_name as recorded_by_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN courses c ON a.course_id = c.id
        LEFT JOIN admins admin ON a.recorded_by = admin.id
        $where_clause
        ORDER BY a.attendance_date DESC, c.course_code, s.full_name";
        
$stmt = $conn->prepare($sql);
if($params){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$attendance_records = $stmt->get_result();

// Get courses for filter and marking
$courses_result = $conn->query("SELECT * FROM courses WHERE status = 'Active' ORDER BY course_code");
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);

// Get students for marking attendance (need to get enrolled students for selected course)
$students_result = $conn->query("SELECT DISTINCT s.id, s.student_id, s.full_name 
                                 FROM students s
                                 INNER JOIN student_enrollments se ON s.id = se.student_id
                                 WHERE s.status = 'Active' AND se.status = 'Enrolled'
                                 ORDER BY s.full_name");
$all_students = $students_result->fetch_all(MYSQLI_ASSOC);

// Get ALL registered students (active) for directory and modal
$registered_students_result = $conn->query("SELECT id, student_id, full_name FROM students WHERE status='Active' ORDER BY full_name");
$registered_students = $registered_students_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN status = 'Excused' THEN 1 ELSE 0 END) as excused_count
    FROM attendance";
if($filter_course){
    $stats_sql .= " WHERE course_id = " . intval($filter_course);
}
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="container">
    <!-- Header -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #10b981 0%, #000000 100%); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
            <div>
                <h1 style="margin: 0; color: white; font-size: 1.75rem;">
                    <i class="fas fa-calendar-check"></i> Attendance Management
                </h1>
                <p style="color: rgba(255,255,255,0.9); margin: 0.5rem 0 0 0; font-size: 1rem;">
                    Track and manage student attendance
                </p>
            </div>
            <?php if($is_manager): ?>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button type="button" onclick="openAddAttendanceModal()" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add Student
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Add Single Attendance Modal -->
<div id="addAttendanceModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add Attendance</h3>
            <button onclick="closeAddAttendanceModal()" class="close-btn">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="add_single_attendance">

            <div class="form-group">
                <label class="form-label">Student *</label>
                <select name="student_id" class="form-input" required>
                    <option value="">Select Student</option>
                    <?php foreach($registered_students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Course *</label>
                <select name="course_id" class="form-input" required>
                    <option value="">Select Course</option>
                    <?php foreach($courses as $course): ?>
                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Date *</label>
                <input type="date" name="attendance_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Status *</label>
                <select name="status" class="form-input" required>
                    <option value="Present">Present</option>
                    <option value="Absent">Absent</option>
                    <option value="Late">Late</option>
                    <option value="Excused">Excused</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-input" rows="3" placeholder="Optional remarks..."></textarea>
            </div>

            <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" onclick="closeAddAttendanceModal()" class="btn btn-secondary" style="flex:1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
    
</div>
    <?php if($msg): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin: 1rem 0;">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="dashboard-grid" style="margin-bottom: 2rem;">
        <div class="dashboard-card">
            <h3><i class="fas fa-list"></i> Total Records</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?= $stats['total_records'] ?? 0 ?></p>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-check-circle"></i> Present</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?= $stats['present_count'] ?? 0 ?></p>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-times-circle"></i> Absent</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--danger-color);"><?= $stats['absent_count'] ?? 0 ?></p>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-clock"></i> Late/Excused</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--warning-color);"><?= ($stats['late_count'] ?? 0) + ($stats['excused_count'] ?? 0) ?></p>
        </div>
    </div>

    <!-- Mark Attendance Form -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-plus-circle"></i> Mark Attendance</h3>
        <form method="post" id="markAttendanceForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="mark_attendance">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Course *</label>
                    <select name="course_id" id="markCourseId" class="form-input" required onchange="loadCourseStudents(this.value)">
                        <option value="">Select Course</option>
                        <?php foreach($courses as $course): ?>
                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="attendance_date" id="attendanceDate" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <div id="studentsList" style="display: none;">
                <h4 style="margin-bottom: 1rem;">Mark Attendance for Students</h4>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="studentsTableBody">
                            <!-- Students will be loaded here via JavaScript -->
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            </div>
        </form>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <form method="get" style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <select name="course_id" class="form-input" style="width: auto; min-width: 200px;">
                <option value="">All Courses</option>
                <?php foreach($courses as $course): ?>
                <option value="<?= $course['id'] ?>" <?= $filter_course == $course['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" class="form-input" style="width: auto;" value="<?= htmlspecialchars($filter_date) ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="attendance.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
        </form>
    </div>

    <!-- Attendance Records Table -->
    <div class="card">
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-list"></i> Attendance Records</h3>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Recorded By</th>
                        <?php if($is_manager): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($attendance_records->num_rows > 0): ?>
                        <?php while($record = $attendance_records->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($record['attendance_date'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($record['course_code']) ?></strong><br>
                                <small style="color: var(--secondary-color);"><?= htmlspecialchars($record['course_name']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($record['student_name']) ?></strong><br>
                                <small style="color: var(--secondary-color);"><?= htmlspecialchars($record['student_code']) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?= $record['status'] === 'Present' ? 'success' : ($record['status'] === 'Absent' ? 'danger' : ($record['status'] === 'Late' ? 'warning' : 'info')) ?>">
                                    <?= htmlspecialchars($record['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($record['remarks'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($record['recorded_by_name'] ?? 'N/A') ?></td>
                            <?php if($is_manager): ?>
                            <td>
                                <button type="button" onclick="showEditModal(<?= json_encode($record, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)" class="btn btn-sm btn-primary" style="margin-right: 0.5rem;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" onclick="deleteAttendance(<?= $record['id'] ?>)" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $is_manager ? '7' : '6' ?>" style="text-align: center; padding: 2rem; color: var(--secondary-color);">
                                No attendance records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Back Button -->
    <div style="text-align: center; margin-top: 2rem;">
        <a href="dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Attendance</h3>
            <button onclick="closeModal()" class="close-btn">&times;</button>
        </div>
        <form method="post" id="editForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="update_attendance">
            <input type="hidden" name="attendance_id" id="editAttendanceId">
            
            <div class="form-group">
                <label class="form-label">Status *</label>
                <select name="status" id="editStatus" class="form-input" required>
                    <option value="Present">Present</option>
                    <option value="Absent">Absent</option>
                    <option value="Late">Late</option>
                    <option value="Excused">Excused</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" id="editRemarks" class="form-input" rows="3"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    overflow-y: auto;
}

.modal-content {
    background: white;
    margin: 2rem auto;
    padding: 2rem;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--secondary-color);
    cursor: pointer;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-info { background: #dbeafe; color: #1e40af; }
</style>

<script>
function openAddAttendanceModal(){
    document.getElementById('addAttendanceModal').style.display = 'block';
}
function closeAddAttendanceModal(){
    document.getElementById('addAttendanceModal').style.display = 'none';
}
// Store all students for JavaScript access
const allStudents = <?= json_encode($all_students) ?>;
const enrolledStudents = {};

// Load enrolled students for a course
<?php
// Pre-load enrolled students for each course
foreach($courses as $course){
    $enrolled_sql = "SELECT s.id, s.student_id, s.full_name 
                     FROM students s
                     INNER JOIN student_enrollments se ON s.id = se.student_id
                     WHERE se.course_id = ? AND s.status = 'Active' AND se.status = 'Enrolled'
                     ORDER BY s.full_name";
    $enrolled_stmt = $conn->prepare($enrolled_sql);
    $enrolled_stmt->bind_param('i', $course['id']);
    $enrolled_stmt->execute();
    $enrolled = $enrolled_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo "enrolledStudents[{$course['id']}] = " . json_encode($enrolled) . ";\n";
}
?>

function loadCourseStudents(courseId) {
    const studentsListDiv = document.getElementById('studentsList');
    const tableBody = document.getElementById('studentsTableBody');
    
    if(!courseId || !enrolledStudents[courseId]){
        studentsListDiv.style.display = 'none';
        return;
    }
    
    const students = enrolledStudents[courseId];
    tableBody.innerHTML = '';
    
    students.forEach(student => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <strong>${student.full_name}</strong><br>
                <small style="color: var(--secondary-color);">${student.student_id}</small>
                <input type="hidden" name="students[${student.id}][student_id]" value="${student.id}">
            </td>
            <td>
                <select name="students[${student.id}][status]" class="form-input" required>
                    <option value="Present" selected>Present</option>
                    <option value="Absent">Absent</option>
                    <option value="Late">Late</option>
                    <option value="Excused">Excused</option>
                </select>
            </td>
            <td>
                <input type="text" name="students[${student.id}][remarks]" class="form-input" placeholder="Optional remarks">
            </td>
        `;
        tableBody.appendChild(row);
    });
    
    studentsListDiv.style.display = 'block';
}

window.showEditModal = function(record) {
    try {
        const modal = document.getElementById('editModal');
        if(!modal){ return; }
        const idEl = document.getElementById('editAttendanceId');
        const statusEl = document.getElementById('editStatus');
        const remarksEl = document.getElementById('editRemarks');
        if(idEl) idEl.value = record.id;
        if(statusEl) statusEl.value = record.status;
        if(remarksEl) remarksEl.value = record.remarks || '';
        modal.style.display = 'block';
    } catch(e){
        console.error('showEditModal error', e);
    }
}

window.closeModal = function() {
    const modal = document.getElementById('editModal');
    if(modal){ modal.style.display = 'none'; }
}

function deleteAttendance(id) {
    if(confirm('Are you sure you want to delete this attendance record?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="delete_attendance">
            <input type="hidden" name="attendance_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if(event.target == modal) {
        closeModal();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

