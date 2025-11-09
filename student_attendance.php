<?php
// student_attendance.php — Student attendance view
$page_title = 'My Attendance';
require_once 'includes/header.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student'){
    header('Location: student_login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Get filter parameters
$filter_course = $_GET['course_id'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query
$where = ["a.student_id = ?"];
$params = [$student_id];
$types = 'i';

if($filter_course){
    $where[] = "a.course_id = ?";
    $params[] = intval($filter_course);
    $types .= 'i';
}

if($filter_date_from){
    $where[] = "a.attendance_date >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if($filter_date_to){
    $where[] = "a.attendance_date <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where);

// Get attendance records
$sql = "SELECT a.*, c.course_name, c.course_code
        FROM attendance a
        JOIN courses c ON a.course_id = c.id
        $where_clause
        ORDER BY a.attendance_date DESC, c.course_code";
        
$stmt = $conn->prepare($sql);
if($params){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$attendance_records = $stmt->get_result();

// Get enrolled courses for filter
$courses_sql = "SELECT DISTINCT c.* 
                FROM courses c
                INNER JOIN student_enrollments se ON c.id = se.course_id
                WHERE se.student_id = ? AND c.status = 'Active'
                ORDER BY c.course_code";
$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param('i', $student_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result();

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN status = 'Excused' THEN 1 ELSE 0 END) as excused_count
    FROM attendance WHERE student_id = ?";
if($filter_course){
    $stats_sql .= " AND course_id = " . intval($filter_course);
}
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('i', $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get student info
$student_sql = "SELECT * FROM students WHERE id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param('i', $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// Calculate attendance percentage
$attendance_percentage = 0;
if($stats['total_records'] > 0){
    $attendance_percentage = (($stats['present_count'] + $stats['late_count'] + $stats['excused_count']) / $stats['total_records']) * 100;
}
?>

<style>
.student-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.student-header h1 {
    margin: 0;
    font-size: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.info-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.info-card h3 {
    color: #667eea;
    font-size: 0.875rem;
    text-transform: uppercase;
    margin: 0 0 0.5rem 0;
}

.info-card .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.badge {
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    transition: width 0.3s ease;
}
</style>

<div class="container">
    <div class="student-header">
        <h1><i class="fas fa-calendar-check"></i> My Attendance</h1>
        <p>Student ID: <?= htmlspecialchars($student['student_id']) ?></p>
    </div>

    <!-- Statistics -->
    <div class="info-grid">
        <div class="info-card">
            <h3>Total Records</h3>
            <div class="value"><?= $stats['total_records'] ?? 0 ?></div>
        </div>
        <div class="info-card">
            <h3>Present</h3>
            <div class="value" style="color: #10b981;"><?= $stats['present_count'] ?? 0 ?></div>
        </div>
        <div class="info-card">
            <h3>Absent</h3>
            <div class="value" style="color: #ef4444;"><?= $stats['absent_count'] ?? 0 ?></div>
        </div>
        <div class="info-card">
            <h3>Late/Excused</h3>
            <div class="value" style="color: #f59e0b;"><?= ($stats['late_count'] ?? 0) + ($stats['excused_count'] ?? 0) ?></div>
        </div>
        <div class="info-card">
            <h3>Attendance Rate</h3>
            <div class="value"><?= number_format($attendance_percentage, 1) ?>%</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $attendance_percentage ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <form method="get" style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <select name="course_id" class="form-input" style="width: auto; min-width: 200px;">
                <option value="">All Courses</option>
                <?php while($course = $courses->fetch_assoc()): ?>
                <option value="<?= $course['id'] ?>" <?= $filter_course == $course['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                </option>
                <?php endwhile; ?>
            </select>
            <input type="date" name="date_from" class="form-input" style="width: auto;" value="<?= htmlspecialchars($filter_date_from) ?>" placeholder="From Date">
            <input type="date" name="date_to" class="form-input" style="width: auto;" value="<?= htmlspecialchars($filter_date_to) ?>" placeholder="To Date">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="student_attendance.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
        </form>
    </div>

    <!-- Attendance Records Table -->
    <div class="card">
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-list"></i> Attendance History</h3>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Remarks</th>
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
                                <span class="badge badge-<?= $record['status'] === 'Present' ? 'success' : ($record['status'] === 'Absent' ? 'danger' : ($record['status'] === 'Late' ? 'warning' : 'info')) ?>">
                                    <?= htmlspecialchars($record['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($record['remarks'] ?: 'N/A') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: var(--secondary-color);">
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
        <a href="student_dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

