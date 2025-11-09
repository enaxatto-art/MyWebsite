<?php
// student_assessments.php — Student assessments page (view own assessments only)
$page_title = 'My Assessments';
require_once 'includes/header.php';

// Check if student is logged in
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student'){
    header('Location: student_login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Get student's assessments with marks
$assessments_sql = "SELECT a.*, c.course_name, c.course_code, m.obtained_marks, m.grade, m.recorded_at as mark_date
                    FROM assessments a
                    INNER JOIN courses c ON a.course_id = c.id
                    INNER JOIN student_enrollments se ON c.id = se.course_id
                    LEFT JOIN marks m ON a.id = m.assessment_id AND m.student_id = ?
                    WHERE se.student_id = ?
                    ORDER BY a.due_date ASC, c.course_name, a.title";
                    
$stmt = $conn->prepare($assessments_sql);
$stmt->bind_param('ii', $student_id, $student_id);
$stmt->execute();
$assessments = $stmt->get_result();

// Get assessment statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT a.id) as total_assessments,
    COUNT(DISTINCT CASE WHEN m.id IS NOT NULL THEN a.id END) as completed_assessments,
    COUNT(DISTINCT CASE WHEN a.due_date < CURDATE() AND m.id IS NULL THEN a.id END) as overdue_assessments,
    AVG(m.obtained_marks) as average_score
    FROM assessments a
    INNER JOIN courses c ON a.course_id = c.id
    INNER JOIN student_enrollments se ON c.id = se.course_id
    LEFT JOIN marks m ON a.id = m.assessment_id AND m.student_id = ?
    WHERE se.student_id = ?";
    
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('ii', $student_id, $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<div class="container">
    <div class="card">
        <h1><i class="fas fa-clipboard-list"></i> My Assessments</h1>
        <p style="color: var(--secondary-color); margin-bottom: 2rem;">
            View your assessments and track your progress
        </p>
        
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['total_assessments'] ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Total Assessments</p>
            </div>
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['completed_assessments'] ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Completed</p>
            </div>
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['overdue_assessments'] ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Overdue</p>
            </div>
            <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['average_score'] ? number_format($stats['average_score'], 1) : 'N/A' ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Avg Score</p>
            </div>
        </div>
        
        <?php if($assessments->num_rows > 0): ?>
        <table class="table data-table">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Assessment</th>
                    <th>Type</th>
                    <th>Total Marks</th>
                    <th>My Score</th>
                    <th>Grade</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($assessment = $assessments->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div>
                            <strong><?= htmlspecialchars($assessment['course_name']) ?></strong><br>
                            <small style="color: var(--secondary-color);"><?= htmlspecialchars($assessment['course_code']) ?></small>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($assessment['title']) ?></td>
                    <td>
                        <span class="badge badge-<?= $assessment['type'] === 'Final' ? 'danger' : ($assessment['type'] === 'Midterm' ? 'warning' : 'primary') ?>">
                            <?= $assessment['type'] ?>
                        </span>
                    </td>
                    <td><?= $assessment['total_marks'] ?></td>
                    <td>
                        <?php if($assessment['obtained_marks'] !== null): ?>
                            <strong><?= $assessment['obtained_marks'] ?></strong>
                        <?php else: ?>
                            <span style="color: var(--secondary-color);">Not submitted</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($assessment['grade']): ?>
                            <span class="badge badge-<?= $assessment['grade'] === 'A' ? 'success' : ($assessment['grade'] === 'B' ? 'primary' : ($assessment['grade'] === 'C' ? 'warning' : 'danger')) ?>">
                                <?= $assessment['grade'] ?>
                            </span>
                        <?php else: ?>
                            <span style="color: var(--secondary-color);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($assessment['due_date']): ?>
                            <?= date('M j, Y', strtotime($assessment['due_date'])) ?>
                            <?php 
                            $due_date = strtotime($assessment['due_date']);
                            $now = time();
                            if($due_date < $now && $assessment['obtained_marks'] === null): ?>
                                <br><small style="color: #dc2626;">Overdue</small>
                            <?php elseif($due_date <= strtotime('+7 days') && $assessment['obtained_marks'] === null): ?>
                                <br><small style="color: #f59e0b;">Due Soon</small>
                            <?php endif; ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($assessment['obtained_marks'] !== null): ?>
                            <span class="badge badge-success">Completed</span>
                        <?php elseif($assessment['due_date'] && strtotime($assessment['due_date']) < time()): ?>
                            <span class="badge badge-danger">Overdue</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; color: #64748b;">
            <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h4 style="margin: 0 0 0.5rem 0;">No Assessments Found</h4>
            <p style="margin: 0;">You don't have any assessments assigned yet. Contact your instructor for more information.</p>
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

.badge-primary {
    background: #dbeafe;
    color: #1e40af;
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
</style>

<?php require_once 'includes/footer.php'; ?>
