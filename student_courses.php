<?php
// student_courses.php — Student courses page (view enrolled courses only)
$page_title = 'My Courses';
require_once 'includes/header.php';

// Check if student is logged in
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student'){
    header('Location: student_login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Get student's enrolled courses
$courses_sql = "SELECT c.*, se.enrollment_date, se.status as enrollment_status, se.final_grade
                FROM courses c
                INNER JOIN student_enrollments se ON c.id = se.course_id
                WHERE se.student_id = ?
                ORDER BY se.enrollment_date DESC";
                
$stmt = $conn->prepare($courses_sql);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$courses = $stmt->get_result();

// Get student's course statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT se.course_id) as total_courses,
    COUNT(DISTINCT CASE WHEN se.status = 'Active' THEN se.course_id END) as active_courses,
    COUNT(DISTINCT CASE WHEN se.final_grade IS NOT NULL THEN se.course_id END) as completed_courses,
    AVG(se.final_grade) as average_grade
    FROM student_enrollments se
    WHERE se.student_id = ?";
    
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('i', $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<div class="container">
    <div class="card">
        <h1><i class="fas fa-book"></i> My Courses</h1>
        <p style="color: var(--secondary-color); margin-bottom: 2rem;">
            View your enrolled courses and academic progress
        </p>
        
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['total_courses'] ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Total Courses</p>
            </div>
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['active_courses'] ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Active Courses</p>
            </div>
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['completed_courses'] ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Completed</p>
            </div>
            <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['average_grade'] ? number_format($stats['average_grade'], 1) : 'N/A' ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Avg Grade</p>
            </div>
        </div>
        
        <?php if($courses->num_rows > 0): ?>
        <table class="table data-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Description</th>
                    <th>Credits</th>
                    <th>Enrolled</th>
                    <th>Status</th>
                    <th>Final Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php while($course = $courses->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($course['course_code']) ?></strong></td>
                    <td><?= htmlspecialchars($course['course_name']) ?></td>
                    <td><?= htmlspecialchars($course['description']) ?></td>
                    <td><?= $course['credits'] ?></td>
                    <td><?= date('M j, Y', strtotime($course['enrollment_date'])) ?></td>
                    <td>
                        <span class="badge badge-<?= $course['enrollment_status'] === 'Active' ? 'success' : 'secondary' ?>">
                            <?= htmlspecialchars($course['enrollment_status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if($course['final_grade']): ?>
                            <span class="badge badge-<?= $course['final_grade'] >= 80 ? 'success' : ($course['final_grade'] >= 60 ? 'warning' : 'danger') ?>">
                                <?= $course['final_grade'] ?>
                            </span>
                        <?php else: ?>
                            <span style="color: var(--secondary-color);">In Progress</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; color: #64748b;">
            <i class="fas fa-book" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h4 style="margin: 0 0 0.5rem 0;">No Courses Enrolled</h4>
            <p style="margin: 0;">You are not enrolled in any courses yet. Contact your administrator for enrollment.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.badge {
    padding: 0.25rem 0.75rem;
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
    background: #fecaca;
    color: #991b1b;
}

.badge-secondary {
    background: #f1f5f9;
    color: #64748b;
}
</style>

<?php require_once 'includes/footer.php'; ?>
