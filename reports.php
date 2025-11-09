<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$page_title = 'Reports';
require_once __DIR__ . '/includes/header.php';
// Page-scoped styles for reports
echo '<link rel="stylesheet" href="assets/css/report-card.css">';

$filter_course = $_GET['course_id'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_student = $_GET['student_id'] ?? '';
// Student ID lookup from input
$filter_student_code = trim($_GET['student_code'] ?? '');

$courses_result = $conn->query("SELECT * FROM courses WHERE status = 'Active' ORDER BY course_code");
$courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];

// Load active students for selector
$students_result = $conn->query("SELECT id, student_id, full_name FROM students WHERE status='Active' ORDER BY full_name");
$students = $students_result ? $students_result->fetch_all(MYSQLI_ASSOC) : [];

// If student_code is provided, resolve to student ID and apply as filter
$student_code_lookup_error = '';
if ($filter_student_code !== '') {
    $st = $conn->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
    $st->bind_param('s', $filter_student_code);
    if ($st->execute()) {
        $res = $st->get_result()->fetch_assoc();
        if ($res) {
            $filter_student = (string)$res['id'];
        } else {
            $student_code_lookup_error = 'No student found for ID: ' . htmlspecialchars($filter_student_code);
            // Force empty results if ID not found by using impossible filter
            $filter_student = '-1';
        }
    }
    $st->close();
}

$has_misconducts = false;
$tbl_chk = $conn->query("SHOW TABLES LIKE 'misconducts'");
if ($tbl_chk && $tbl_chk->num_rows > 0) { $has_misconducts = true; }

$where = [];
$params = [];
$types = '';

// Attendance-based optional filters
if ($filter_course) { $where[] = "a.course_id = ?"; $params[] = intval($filter_course); $types .= 'i'; }
if ($filter_date_from) { $where[] = "a.attendance_date >= ?"; $params[] = $filter_date_from; $types .= 's'; }
if ($filter_date_to) { $where[] = "a.attendance_date <= ?"; $params[] = $filter_date_to; $types .= 's'; }
if ($filter_status && in_array($filter_status, ['Present','Absent','Late','Excused'], true)) {
    $where[] = "a.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_student) { $where[] = "s.id = ?"; $params[] = intval($filter_student); $types .= 'i'; }

// Always include only active students
$where[] = "s.status = 'Active'";

// Final WHERE SQL
$where_clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$mis_where = [];
$mis_params = [];
$mis_types = '';
if ($filter_date_from) { $mis_where[] = "incident_date >= ?"; $mis_params[] = $filter_date_from; $mis_types .= 's'; }
if ($filter_date_to) { $mis_where[] = "incident_date <= ?"; $mis_params[] = $filter_date_to; $mis_types .= 's'; }
$mis_where_clause = $mis_where ? ('WHERE ' . implode(' AND ', $mis_where)) : '';

$misconduct_sql = $has_misconducts
  ? "SELECT student_id, COUNT(*) AS misconduct_count FROM misconducts $mis_where_clause GROUP BY student_id"
  : null;

$sql = "SELECT s.id AS sid, s.student_id AS student_code, s.full_name,
               COALESCE(SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END),0) AS late_days,
               COALESCE(SUM(CASE WHEN a.status = 'Excused' THEN 1 ELSE 0 END),0) AS leave_days
        FROM students s
        LEFT JOIN attendance a ON a.student_id = s.id
        $where_clause
        GROUP BY s.id, s.student_id, s.full_name
        ORDER BY s.full_name";

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$attendance_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$misconduct_map = [];
if ($has_misconducts) {
    if ($filter_course) {
        $course_filter_join = $conn->prepare("SELECT m.student_id, COUNT(*) AS cnt
            FROM misconducts m
            JOIN student_enrollments se ON se.student_id = m.student_id AND se.status='Enrolled'
            WHERE se.course_id = ? " . ($mis_where ? ('AND ' . implode(' AND ', $mis_where)) : '') . "
            GROUP BY m.student_id");
        $bind_types = 'i' . $mis_types;
        if ($mis_params) { $course_filter_join->bind_param($bind_types, $filter_course, ...$mis_params); }
        else { $course_filter_join->bind_param('i', $filter_course); }
        $course_filter_join->execute();
        $res = $course_filter_join->get_result();
        while ($row = $res->fetch_assoc()) { $misconduct_map[$row['student_id']] = (int)$row['cnt']; }
        $course_filter_join->close();
    } else {
        if ($misconduct_sql) {
            $mstmt = $conn->prepare($misconduct_sql);
            if ($mis_params) { $mstmt->bind_param($mis_types, ...$mis_params); }
            $mstmt->execute();
            $res = $mstmt->get_result();
            while ($row = $res->fetch_assoc()) { $misconduct_map[$row['student_id']] = (int)$row['misconduct_count']; }
            $mstmt->close();
        }
    }
}

// If a specific student is selected, prepare detailed info (ID, Name, Attendance, Fee)
$student_detail = null;
if (!empty($filter_student) && intval($filter_student) > 0) {
    // Student basic info
    $stu_stmt = $conn->prepare("SELECT id, student_id AS student_code, full_name FROM students WHERE id = ? LIMIT 1");
    $sid = intval($filter_student);
    $stu_stmt->bind_param('i', $sid);
    if ($stu_stmt->execute()) {
        $student_detail = $stu_stmt->get_result()->fetch_assoc();
    }
    $stu_stmt->close();

    if ($student_detail) {
        // Attendance totals for the student
        $att_stmt = $conn->prepare("SELECT 
            SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN status='Excused' THEN 1 ELSE 0 END) AS excused_count
        FROM attendance WHERE student_id = ?");
        $att_stmt->bind_param('i', $sid);
        $att_stmt->execute();
        $student_detail['attendance'] = $att_stmt->get_result()->fetch_assoc() ?: ['present_count'=>0,'absent_count'=>0,'late_count'=>0,'excused_count'=>0];
        $att_stmt->close();

        // Fee totals if table exists
        $student_detail['fees'] = ['total_paid'=>0,'total_pending'=>0,'total_txns'=>0];
        $fee_tbl = $conn->query("SHOW TABLES LIKE 'fee_payments'");
        if ($fee_tbl && $fee_tbl->num_rows > 0) {
            $fee_stmt = $conn->prepare("SELECT 
                SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END) AS total_paid,
                SUM(CASE WHEN status='Pending' THEN amount ELSE 0 END) AS total_pending,
                COUNT(*) AS total_txns
            FROM fee_payments WHERE student_id = ?");
            $fee_stmt->bind_param('i', $sid);
            $fee_stmt->execute();
            $fees = $fee_stmt->get_result()->fetch_assoc();
            if ($fees) { $student_detail['fees'] = $fees; }
            $fee_stmt->close();
        }

        // Enrolled courses
        $courses_stmt = $conn->prepare("SELECT c.course_code, c.course_name, se.enrollment_date, se.status as enrollment_status, se.final_grade
                                         FROM student_enrollments se
                                         JOIN courses c ON se.course_id = c.id
                                         WHERE se.student_id = ?
                                         ORDER BY c.course_code");
        $courses_stmt->bind_param('i', $sid);
        $courses_stmt->execute();
        $student_detail['courses'] = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $courses_stmt->close();

        // Recent marks with assessment and course
        $marks_stmt = $conn->prepare("SELECT c.course_code, c.course_name, a.title AS assessment, a.type, a.weight, a.total_marks, a.due_date, m.obtained_marks, m.grade, m.recorded_at
                                      FROM marks m
                                      JOIN assessments a ON m.assessment_id = a.id
                                      JOIN courses c ON a.course_id = c.id
                                      WHERE m.student_id = ?
                                      ORDER BY m.recorded_at DESC
                                      LIMIT 20");
        $marks_stmt->bind_param('i', $sid);
        $marks_stmt->execute();
        $student_detail['marks'] = $marks_stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $marks_stmt->close();

        // Attendance per course for the student (to compute attendance contribution)
        $att_course_stmt = $conn->prepare("SELECT course_id, 
                SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present_count,
                COUNT(*) AS total_count
            FROM attendance WHERE student_id = ? GROUP BY course_id");
        $att_course_stmt->bind_param('i', $sid);
        $att_course_stmt->execute();
        $att_course_map = [];
        $res_ac = $att_course_stmt->get_result();
        while($r = $res_ac->fetch_assoc()){ $att_course_map[(int)$r['course_id']] = $r; }
        $att_course_stmt->close();

        // Map course_id by code for joining attendance map later
        $course_id_map = [];
        $cid_stmt = $conn->prepare("SELECT c.id, c.course_code FROM student_enrollments se JOIN courses c ON se.course_id=c.id WHERE se.student_id=?");
        $cid_stmt->bind_param('i',$sid);
        $cid_stmt->execute();
        $res_cid = $cid_stmt->get_result();
        while($r = $res_cid->fetch_assoc()){ $course_id_map[$r['course_code']] = (int)$r['id']; }
        $cid_stmt->close();

        // Build report rows per course
        $report_rows = [];
        foreach($student_detail['marks'] as $m){
            $code = $m['course_code'];
            if(!isset($report_rows[$code])){
                $report_rows[$code] = [
                    'course_name' => $m['course_name'],
                    'quiz1' => null,
                    'quiz2' => null,
                    'midterm' => null,
                    'final' => null,
                    'attendance' => null,
                    'total' => 0.0,
                ];
            }
            // Contribution using assessment weight (%). If weight is null, treat as 0.
            $weight = isset($m['weight']) ? (float)$m['weight'] : 0.0; // e.g., 20 for 20%
            $obt = (float)$m['obtained_marks'];
            $tot = max(1.0, (float)$m['total_marks']);
            $contrib = ($obt / $tot) * $weight; // percentage points
            if($m['type'] === 'Quiz'){
                // Place into quiz1 then quiz2 by date order
                if($report_rows[$code]['quiz1'] === null){
                    $report_rows[$code]['quiz1'] = $contrib;
                } else {
                    $report_rows[$code]['quiz2'] = $contrib;
                }
            } elseif($m['type'] === 'Midterm'){
                $report_rows[$code]['midterm'] = $contrib;
            } elseif($m['type'] === 'Final'){
                $report_rows[$code]['final'] = $contrib;
            } else {
                // Other types can be added to total directly
                $report_rows[$code]['total'] += $contrib;
            }
        }

        // Compute attendance contribution at fixed 10% if attendance data exists
        foreach($report_rows as $code => &$row){
            $cid = $course_id_map[$code] ?? null;
            if($cid && isset($att_course_map[$cid])){
                $present = (int)$att_course_map[$cid]['present_count'];
                $total = max(1, (int)$att_course_map[$cid]['total_count']);
                $attendance_rate = $present / $total; // 0..1
                $row['attendance'] = $attendance_rate * 10.0; // 10%
            }
            // Sum components
            foreach(['quiz1','quiz2','midterm','final','attendance'] as $k){ if(isset($row[$k]) && $row[$k] !== null){ $row['total'] += (float)$row[$k]; } }
        }
        unset($row);
        $student_detail['report_rows'] = $report_rows;
    }
}
?>

<div class="container">
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #10b981 0%, #000000 100%); color: white;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem;">
            <div>
                <h1 style="margin:0; color:white; font-size:1.75rem;"><i class="fas fa-file-lines"></i> Reports</h1>
                <p style="margin:0.5rem 0 0 0; color:rgba(255,255,255,0.9);">Per-student summary of Late days, Leave days, and Misconducts</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
        <form id="reportsFilterForm" method="get" class="reports-form">
            <select name="course_id" class="form-input" style="width:auto; min-width:220px;">
                <option value="">All Courses</option>
                <?php foreach($courses as $course): ?>
                <option value="<?= $course['id'] ?>" <?= $filter_course == $course['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <input type="text" name="student_code" class="form-input" style="width:180px;" placeholder="Student ID" value="<?= htmlspecialchars($filter_student_code) ?>">
                <button type="submit" class="btn btn-secondary" title="Find by ID"><i class="fas fa-search"></i></button>
            </div>
            <select name="student_id" class="form-input" style="width:auto; min-width:240px;">
                <option value="">All Students</option>
                <?php foreach($students as $stu): ?>
                <option value="<?= $stu['id'] ?>" <?= $filter_student == $stu['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['student_id']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-input" style="width:auto; min-width:200px;">
                <option value="">All Statuses</option>
                <option value="Present" <?= $filter_status==='Present' ? 'selected' : '' ?>>Present</option>
                <option value="Absent" <?= $filter_status==='Absent' ? 'selected' : '' ?>>Absent</option>
                <option value="Late" <?= $filter_status==='Late' ? 'selected' : '' ?>>Late</option>
                <option value="Excused" <?= $filter_status==='Excused' ? 'selected' : '' ?>>Excused</option>
            </select>
            <input type="date" name="date_to" class="form-input" style="width:auto;" value="<?= htmlspecialchars($filter_date_to) ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="reports.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
        </form>
    </div>
    <?php if ($student_code_lookup_error): ?>
        <div class="alert alert-danger" style="margin-top:-1rem; margin-bottom:1rem;"><?= $student_code_lookup_error ?></div>
    <?php endif; ?>

    <?php if ($student_detail): ?>
    <div id="studentDetails" class="card" style="margin-bottom:1.5rem;">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-id-card"></i> University Student Profile</h3>
        <div class="info-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:1rem;">
            <div class="info-card" style="background:white; padding:1rem; border-radius:12px;">
                <h4 style="margin:0 0 .25rem 0; color:#64748b; font-size:.85rem; text-transform:uppercase;">ID</h4>
                <div style="font-weight:700; color:#111827;"><?= htmlspecialchars($student_detail['student_code']) ?></div>
            </div>
            <div class="info-card" style="background:white; padding:1rem; border-radius:12px;">
                <h4 style="margin:0 0 .25rem 0; color:#64748b; font-size:.85rem; text-transform:uppercase;">Name</h4>
                <div style="font-weight:700; color:#111827;"><?= htmlspecialchars($student_detail['full_name']) ?></div>
            </div>
            <div class="info-card" style="background:white; padding:1rem; border-radius:12px;">
                <h4 style="margin:0 0 .25rem 0; color:#64748b; font-size:.85rem; text-transform:uppercase;">Attendance</h4>
                <div>
                    <span class="badge badge-success">Present: <?= (int)($student_detail['attendance']['present_count'] ?? 0) ?></span>
                    <span class="badge badge-danger" style="margin-left:.5rem;">Absent: <?= (int)($student_detail['attendance']['absent_count'] ?? 0) ?></span>
                    <span class="badge badge-warning" style="margin-left:.5rem;">Late: <?= (int)($student_detail['attendance']['late_count'] ?? 0) ?></span>
                    <span class="badge badge-info" style="margin-left:.5rem;">Excused: <?= (int)($student_detail['attendance']['excused_count'] ?? 0) ?></span>
                </div>
            </div>
            <div class="info-card" style="background:white; padding:1rem; border-radius:12px;">
                <h4 style="margin:0 0 .25rem 0; color:#64748b; font-size:.85rem; text-transform:uppercase;">Fee</h4>
                <div>
                    <div><strong>Total Paid:</strong> $<?= number_format((float)($student_detail['fees']['total_paid'] ?? 0), 2) ?></div>
                    <div><strong>Pending:</strong> $<?= number_format((float)($student_detail['fees']['total_pending'] ?? 0), 2) ?></div>
                    <div><strong>Transactions:</strong> <?= (int)($student_detail['fees']['total_txns'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrolled Courses (University style) -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-book"></i> Enrolled Courses</h3>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Enrollment Date</th>
                        <th>Final Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($student_detail['courses'])): ?>
                        <?php foreach($student_detail['courses'] as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['course_code']) ?></strong><br><small style="color:var(--secondary-color);"><?= htmlspecialchars($c['course_name']) ?></small></td>
                            <td><?= htmlspecialchars($c['enrollment_status'] ?: 'Enrolled') ?></td>
                            <td><?= $c['enrollment_date'] ? date('M j, Y', strtotime($c['enrollment_date'])) : '—' ?></td>
                            <td><?= htmlspecialchars($c['final_grade'] ?: 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--secondary-color); padding:1rem;">No courses found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assessment Marks (University style) -->
    <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-chart-line"></i> Assessment Marks</h3>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Assessment</th>
                        <th>Obtained / Total</th>
                        <th>Grade</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($student_detail['marks'])): ?>
                        <?php foreach($student_detail['marks'] as $m): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['course_code']) ?></strong><br><small style="color:var(--secondary-color);"><?= htmlspecialchars($m['course_name']) ?></small></td>
                            <td><?= htmlspecialchars($m['assessment']) ?></td>
                            <td><strong><?= number_format((float)$m['obtained_marks'], 2) ?></strong> / <?= (int)$m['total_marks'] ?></td>
                            <td><?= htmlspecialchars($m['grade'] ?: 'N/A') ?></td>
                            <td><?= date('M j, Y', strtotime($m['recorded_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; color:var(--secondary-color); padding:1rem;">No assessment records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- University Report Card (printable) -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin-bottom:1rem;"><i class="fas fa-file-invoice"></i> University Report Card</h3>
            <button type="button" class="btn btn-secondary js-print"><i class="fas fa-print"></i> Print</button>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Quiz 1 20%</th>
                        <th>Midterm 20%</th>
                        <th>Attendance 10%</th>
                        <th>Quiz 2 20%</th>
                        <th>Final 30%</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($student_detail['report_rows'])): ?>
                        <?php foreach($student_detail['report_rows'] as $code => $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($code) ?></strong><br><small style="color:var(--secondary-color);"><?= htmlspecialchars($r['course_name']) ?></small></td>
                            <td><?= $r['quiz1'] !== null ? number_format($r['quiz1'],2) : '—' ?></td>
                            <td><?= $r['midterm'] !== null ? number_format($r['midterm'],2) : '—' ?></td>
                            <td><?= $r['attendance'] !== null ? number_format($r['attendance'],2) : '—' ?></td>
                            <td><?= $r['quiz2'] !== null ? number_format($r['quiz2'],2) : '—' ?></td>
                            <td><?= $r['final'] !== null ? number_format($r['final'],2) : '—' ?></td>
                            <td><strong><?= number_format($r['total'],2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php
                        // Totals row (average and sum similar to sample)
                        $avgTotal = 0; $countRows = count($student_detail['report_rows']);
                        foreach($student_detail['report_rows'] as $r){ $avgTotal += (float)$r['total']; }
                        $avgTotal = $countRows ? $avgTotal / $countRows : 0;
                        ?>
                        <tr>
                            <td><strong>Average</strong></td>
                            <td colspan="5"></td>
                            <td><strong><?= number_format($avgTotal,2) ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--secondary-color); padding:1rem;">No report data available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-bottom:1rem;"><i class="fas fa-table"></i> Student Summary</h3>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Late Days</th>
                        <th>Leave Days</th>
                        <th>Misconducts</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($attendance_summary): ?>
                    <?php foreach($attendance_summary as $row): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                            <small style="color: var(--secondary-color);"><?= htmlspecialchars($row['student_code']) ?></small>
                        </td>
                        <td><span class="badge badge-warning"><?= (int)$row['late_days'] ?></span></td>
                        <td><span class="badge badge-info"><?= (int)$row['leave_days'] ?></span></td>
                        <td><span class="badge badge-danger"><?= (int)($misconduct_map[$row['sid']] ?? 0) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding:2rem; color: var(--secondary-color);">No data found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="text-align:center; margin-top:2rem;">
        <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</div>

<script src="assets/js/report-card.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
